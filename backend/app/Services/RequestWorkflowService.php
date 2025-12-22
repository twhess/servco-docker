<?php

namespace App\Services;

use App\Models\PartsRequest;
use App\Models\PartsRequestAction;
use App\Models\PartsRequestStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestWorkflowService
{
    protected RunSchedulerService $runScheduler;
    protected NotificationService $notificationService;
    protected InventoryIntegrationService $inventoryService;

    public function __construct(
        RunSchedulerService $runScheduler,
        NotificationService $notificationService,
        InventoryIntegrationService $inventoryService
    ) {
        $this->runScheduler = $runScheduler;
        $this->notificationService = $notificationService;
        $this->inventoryService = $inventoryService;
    }

    /**
     * Execute an action on a request
     *
     * @param PartsRequest $request
     * @param string $actionName
     * @param array $data ['note' => string, 'photo' => file, 'lat' => float, 'lng' => float]
     * @param User $user
     * @return void
     * @throws \Exception
     */
    public function executeAction(PartsRequest $request, string $actionName, array $data, User $user): void
    {
        // Find the action definition
        $action = PartsRequestAction::active()
            ->forRequestType($request->request_type_id)
            ->fromStatus($request->status_id)
            ->where('action_name', $actionName)
            ->firstOrFail();

        // Validate user has permission for this action
        $userRole = $this->determineUserRole($user);
        if ($action->actor_role !== $userRole) {
            throw new \Exception("User does not have permission to perform this action");
        }

        // Validate required fields
        if ($action->requires_note && empty($data['note'])) {
            throw new \Exception("Note is required for this action");
        }

        if ($action->requires_photo && empty($data['photo'])) {
            throw new \Exception("Photo is required for this action");
        }

        DB::beginTransaction();
        try {
            // Perform action-specific logic
            $this->performAction($request, $actionName, $data, $user);

            // Update status
            $request->update([
                'status_id' => $action->to_status_id,
                'last_modified_by_user_id' => $user->id,
                'last_modified_at' => now(),
                'updated_by' => $user->id,
            ]);

            // Create event
            $request->events()->create([
                'event_type' => $actionName,
                'notes' => $data['note'] ?? null,
                'event_at' => now(),
                'user_id' => $user->id,
            ]);

            // Save photo if provided
            if (!empty($data['photo'])) {
                $this->savePhoto($request, $actionName, $data['photo']);
            }

            // Save location if provided
            if (!empty($data['lat']) && !empty($data['lng'])) {
                $this->saveLocation($request, $actionName, $data['lat'], $data['lng']);
            }

            DB::commit();
            Log::info("Action '{$actionName}' executed on request #{$request->id} by user #{$user->id}");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to execute action '{$actionName}' on request #{$request->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform action-specific business logic
     */
    private function performAction(PartsRequest $request, string $actionName, array $data, User $user): void
    {
        switch ($actionName) {
            case 'ready_to_transfer':
                $this->handleReadyToTransfer($request, $user, $data['note'] ?? null);
                break;

            case 'not_available':
                $this->handleNotAvailable($request, $user, $data['note'] ?? 'Part not available');
                break;

            case 'pickup':
                $this->handlePickup($request, $user, $data['photo'] ?? null);
                break;

            case 'not_ready':
                $this->handleNotReady($request, $user, $data['note'] ?? 'Part not ready');
                break;

            case 'deliver':
                $this->handleDeliver($request, $user, $data['photo'] ?? null);
                break;

            case 'unable_to_deliver':
                $this->handleUnableToDeliver($request, $user, $data['note'] ?? 'Unable to deliver');
                break;

            default:
                // Generic action, no special handling needed
                break;
        }
    }

    /**
     * Handle "Ready to Transfer" action (shop staff)
     */
    public function handleReadyToTransfer(PartsRequest $request, User $user, ?string $note): void
    {
        Log::info("Request #{$request->id} marked as ready to transfer by user #{$user->id}");

        // If not already assigned to a run, auto-assign it now
        if (!$request->run_instance_id) {
            $this->runScheduler->autoAssignRequest($request);
        }
    }

    /**
     * Handle "Not Available" action (shop staff)
     */
    public function handleNotAvailable(PartsRequest $request, User $user, string $reason): void
    {
        Log::info("Request #{$request->id} marked as not available: {$reason}");

        // Notify requester
        $this->notificationService->notifyNotAvailable($request, $reason);

        // Unassign from run if assigned
        if ($request->run_instance_id) {
            $this->runScheduler->unassignRequest($request, 'Part not available');
        }
    }

    /**
     * Handle "Pickup" action (runner)
     */
    public function handlePickup(PartsRequest $request, User $user, $photo): void
    {
        Log::info("Request #{$request->id} picked up by user #{$user->id}");

        // Create item movement if inventory tracking enabled
        if ($request->item_id) {
            $this->inventoryService->createItemMovementFromPickup($request, $user);
        }

        // Notify if configured
        if ($request->slack_notify_pickup) {
            // TODO: Send pickup notification
        }
    }

    /**
     * Handle "Not Ready" action (runner - vendor pickup)
     */
    public function handleNotReady(PartsRequest $request, User $user, string $reason): void
    {
        Log::info("Request #{$request->id} marked as not ready: {$reason}");

        // Reassign to next run on same route
        $reassigned = $this->runScheduler->reassignToNextRun($request);

        if (!$reassigned) {
            // If no next run available, unassign and notify dispatcher
            $this->runScheduler->unassignRequest($request, 'Part not ready, no next run available');
            // TODO: Notify dispatcher for manual handling
        }
    }

    /**
     * Handle "Deliver" action (runner)
     */
    public function handleDeliver(PartsRequest $request, User $user, $photo): void
    {
        Log::info("Request #{$request->id} delivered by user #{$user->id}");

        // Create item movement if inventory tracking enabled
        if ($request->item_id) {
            $this->inventoryService->createItemMovementFromDelivery($request, $user);
        }

        // Notify if configured
        if ($request->slack_notify_delivery) {
            // TODO: Send delivery notification
        }
    }

    /**
     * Handle "Unable to Deliver" action (runner - customer delivery)
     */
    public function handleUnableToDeliver(PartsRequest $request, User $user, string $reason): void
    {
        Log::info("Request #{$request->id} unable to deliver: {$reason}");

        DB::transaction(function () use ($request, $user, $reason) {
            // Create return transfer request (back to origin)
            $returnRequest = PartsRequest::create([
                'request_type_id' => $request->requestType->where('name', 'transfer')->first()->id ?? $request->request_type_id,
                'reference_number' => PartsRequest::generateReferenceNumber(),
                'origin_location_id' => $request->receiving_location_id, // Customer location
                'receiving_location_id' => $request->origin_location_id, // Original shop
                'urgency_id' => $request->urgency_id,
                'status_id' => PartsRequestStatus::where('name', 'new')->first()->id,
                'details' => "Return from failed delivery - {$request->reference_number}",
                'parent_request_id' => $request->id,
                'requested_by_user_id' => $user->id,
                'requested_at' => now(),
                'item_id' => $request->item_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Auto-assign return request to current run if possible
            if ($request->run_instance_id) {
                $returnRequest->update([
                    'run_instance_id' => $request->run_instance_id,
                ]);
            }

            Log::info("Return request #{$returnRequest->id} created for failed delivery #{$request->id}");
        });

        // Notify requester and customer
        $this->notificationService->notifyUnableToDeliver($request, $reason);
    }

    /**
     * Get available actions for a request based on current state and user
     */
    public function getAvailableActions(PartsRequest $request, User $user): \Illuminate\Support\Collection
    {
        return $request->availableActions($user);
    }

    /**
     * Determine user's role for action permissions
     */
    private function determineUserRole(User $user): string
    {
        if ($user->hasPermission('parts_requests.assign_to_run')) {
            return 'dispatcher';
        }

        if ($user->hasPermission('parts_requests.pickup')) {
            return 'runner';
        }

        if ($user->hasPermission('parts_requests.stage_transfer')) {
            return 'shop_staff';
        }

        return 'shop_staff'; // Default fallback
    }

    /**
     * Save photo for an action
     */
    private function savePhoto(PartsRequest $request, string $stage, $photo): void
    {
        // TODO: Implement photo upload logic
        // This should use the existing PartsRequestPhoto model
        Log::info("Photo saved for request #{$request->id} stage: {$stage}");
    }

    /**
     * Save GPS location for an action
     */
    private function saveLocation(PartsRequest $request, string $context, float $lat, float $lng): void
    {
        $request->locations()->create([
            'latitude' => $lat,
            'longitude' => $lng,
            'context' => $context,
            'captured_at' => now(),
        ]);

        Log::info("Location captured for request #{$request->id}: {$lat}, {$lng}");
    }
}
