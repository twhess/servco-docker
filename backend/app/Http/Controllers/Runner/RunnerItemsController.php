<?php

namespace App\Http\Controllers\Runner;

use App\Http\Controllers\Controller;
use App\Models\PartsRequest;
use App\Models\PartsRequestActivity;
use App\Models\PartsRequestPhoto;
use App\Models\PartsRequestStatus;
use App\Models\RunInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Controller for runner item (parts request) management.
 */
class RunnerItemsController extends Controller
{
    /**
     * List items for a run, optionally filtered by stop.
     */
    public function index(Request $request, RunInstance $run): JsonResponse
    {
        $user = $request->user();

        // Ensure runner is assigned to this run
        if ($run->assigned_runner_user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not assigned to this run.',
            ], 403);
        }

        $stopId = $request->input('stop_id');
        $showCompleted = $request->boolean('show_completed', true);

        $query = $run->requests()
            ->with([
                'status',
                'urgency',
                'originLocation',
                'receivingLocation',
                'pickupStop.location',
                'dropoffStop.location',
                'photos',
                'vendor',
            ])
            ->visibleToRunner();

        // Filter by stop if provided
        if ($stopId) {
            $query->where(function ($q) use ($stopId) {
                $q->where('pickup_stop_id', $stopId)
                    ->orWhere('dropoff_stop_id', $stopId);
            });
        }

        // Optionally exclude completed items
        if (!$showCompleted) {
            $query->whereHas('status', function ($q) {
                $q->whereNotIn('name', ['delivered', 'cancelled', 'exception']);
            });
        }

        $items = $query->get()->map(fn($item) => $this->formatItemForRunner($item, $stopId));

        return response()->json([
            'data' => $items,
            'filters' => [
                'stop_id' => $stopId,
                'show_completed' => $showCompleted,
            ],
        ]);
    }

    /**
     * Get a single item's details.
     */
    public function show(Request $request, PartsRequest $partsRequest): JsonResponse
    {
        $user = $request->user();

        // Ensure user can access this item
        if (!$this->canAccessItem($user, $partsRequest)) {
            return response()->json([
                'message' => 'You cannot access this item.',
            ], 403);
        }

        $partsRequest->load([
            'status',
            'urgency',
            'originLocation',
            'receivingLocation',
            'pickupStop.location',
            'dropoffStop.location',
            'photos',
            'runInstance',
            'vendor',
            'items',
            'documents',
            'notes.user',
        ]);

        return response()->json([
            'data' => $this->formatItemForRunner($partsRequest),
        ]);
    }

    /**
     * Update item status with workflow validation.
     */
    public function updateStatus(Request $request, PartsRequest $partsRequest): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|exists:parts_request_statuses,name',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if (!$this->canAccessItem($user, $partsRequest)) {
            return response()->json([
                'message' => 'You cannot update this item.',
            ], 403);
        }

        $newStatusName = $request->input('status');
        $newStatus = PartsRequestStatus::where('name', $newStatusName)->first();

        if (!$newStatus) {
            return response()->json([
                'message' => 'Invalid status.',
            ], 422);
        }

        // Validate workflow transition
        $validTransitions = $this->getValidTransitions($partsRequest->status?->name);
        if (!in_array($newStatusName, $validTransitions)) {
            return response()->json([
                'message' => "Cannot transition from '{$partsRequest->status?->name}' to '{$newStatusName}'.",
                'valid_transitions' => $validTransitions,
            ], 422);
        }

        // Check photo requirements
        if ($newStatusName === 'picked_up' && !$partsRequest->hasPickupPhoto()) {
            return response()->json([
                'message' => 'A pickup photo is required before marking as picked up.',
                'requires_photo' => 'pickup',
            ], 422);
        }

        if ($newStatusName === 'delivered' && !$partsRequest->hasDeliveryPhoto()) {
            return response()->json([
                'message' => 'A delivery photo is required before marking as delivered.',
                'requires_photo' => 'delivery',
            ], 422);
        }

        $fromStatus = $partsRequest->status;

        DB::beginTransaction();
        try {
            // Update status
            $partsRequest->update([
                'status_id' => $newStatus->id,
                'last_modified_by_user_id' => $user->id,
                'last_modified_at' => now(),
            ]);

            // Create activity log
            PartsRequestActivity::create([
                'parts_request_id' => $partsRequest->id,
                'from_status_id' => $fromStatus?->id,
                'to_status_id' => $newStatus->id,
                'actor_user_id' => $user->id,
                'notes' => $request->input('notes'),
                'stop_id' => $partsRequest->current_stop_id ?? null,
                'run_id' => $partsRequest->run_instance_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            DB::commit();

            Log::info('RunnerItems: Status updated', [
                'request_id' => $partsRequest->id,
                'from_status' => $fromStatus?->name,
                'to_status' => $newStatusName,
                'runner_id' => $user->id,
            ]);

            $partsRequest->refresh();
            $partsRequest->load(['status', 'photos']);

            return response()->json([
                'message' => 'Status updated successfully.',
                'data' => $this->formatItemForRunner($partsRequest),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('RunnerItems: Failed to update status', [
                'request_id' => $partsRequest->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update status.',
            ], 500);
        }
    }

    /**
     * Upload a photo for an item.
     */
    public function uploadPhoto(Request $request, PartsRequest $partsRequest): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:10240', // 10MB max
            'type' => 'required|in:pickup,delivery,exception,other',
        ]);

        $user = $request->user();

        if (!$this->canAccessItem($user, $partsRequest)) {
            return response()->json([
                'message' => 'You cannot upload photos for this item.',
            ], 403);
        }

        try {
            $file = $request->file('photo');
            $type = $request->input('type');

            // Generate unique filename
            $filename = sprintf(
                '%s_%s_%s_%s.%s',
                $partsRequest->id,
                $type,
                now()->format('YmdHis'),
                uniqid(),
                $file->getClientOriginalExtension()
            );

            // Store in parts-photos directory
            $path = $file->storeAs(
                'parts-photos/' . $partsRequest->id,
                $filename,
                'public'
            );

            // Create photo record
            $photo = PartsRequestPhoto::create([
                'parts_request_id' => $partsRequest->id,
                'type' => $type,
                'file_path' => $path,
                'taken_at' => now(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            Log::info('RunnerItems: Photo uploaded', [
                'request_id' => $partsRequest->id,
                'photo_id' => $photo->id,
                'type' => $type,
                'runner_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Photo uploaded successfully.',
                'photo' => [
                    'id' => $photo->id,
                    'type' => $photo->type,
                    'url' => Storage::url($path),
                    'taken_at' => $photo->taken_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('RunnerItems: Failed to upload photo', [
                'request_id' => $partsRequest->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to upload photo.',
            ], 500);
        }
    }

    /**
     * Add a note to a parts request.
     */
    public function addNote(Request $request, PartsRequest $partsRequest): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $user = $request->user();

        if (!$this->canAccessItem($user, $partsRequest)) {
            return response()->json([
                'message' => 'You cannot add notes to this item.',
            ], 403);
        }

        try {
            $note = \App\Models\PartsRequestNote::create([
                'parts_request_id' => $partsRequest->id,
                'content' => $request->input('content'),
                'user_id' => $user->id,
                'is_edited' => false,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            Log::info('RunnerItems: Note added', [
                'request_id' => $partsRequest->id,
                'note_id' => $note->id,
                'runner_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Note added successfully.',
                'note' => [
                    'id' => $note->id,
                    'content' => $note->content,
                    'user_name' => $user->name,
                    'created_at' => $note->created_at->toIso8601String(),
                    'is_edited' => false,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('RunnerItems: Failed to add note', [
                'request_id' => $partsRequest->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to add note.',
            ], 500);
        }
    }

    /**
     * Mark an item as exception with reason.
     */
    public function markException(Request $request, PartsRequest $partsRequest): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        $user = $request->user();

        if (!$this->canAccessItem($user, $partsRequest)) {
            return response()->json([
                'message' => 'You cannot update this item.',
            ], 403);
        }

        $exceptionStatus = PartsRequestStatus::where('name', 'exception')->first();
        if (!$exceptionStatus) {
            return response()->json([
                'message' => 'Exception status not configured.',
            ], 500);
        }

        $fromStatus = $partsRequest->status;

        DB::beginTransaction();
        try {
            // Handle photo upload if provided
            $photoId = null;
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $filename = sprintf(
                    '%s_exception_%s_%s.%s',
                    $partsRequest->id,
                    now()->format('YmdHis'),
                    uniqid(),
                    $file->getClientOriginalExtension()
                );

                $path = $file->storeAs(
                    'parts-photos/' . $partsRequest->id,
                    $filename,
                    'public'
                );

                $photo = PartsRequestPhoto::create([
                    'parts_request_id' => $partsRequest->id,
                    'type' => 'exception',
                    'file_path' => $path,
                    'taken_at' => now(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $photoId = $photo->id;
            }

            // Update status
            $partsRequest->update([
                'status_id' => $exceptionStatus->id,
                'last_modified_by_user_id' => $user->id,
                'last_modified_at' => now(),
            ]);

            // Create activity log with reason
            $activity = PartsRequestActivity::create([
                'parts_request_id' => $partsRequest->id,
                'from_status_id' => $fromStatus?->id,
                'to_status_id' => $exceptionStatus->id,
                'actor_user_id' => $user->id,
                'notes' => 'Exception: ' . $request->input('reason'),
                'stop_id' => $partsRequest->current_stop_id ?? null,
                'run_id' => $partsRequest->run_instance_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Link photo to activity if uploaded
            if ($photoId) {
                PartsRequestPhoto::where('id', $photoId)->update([
                    'activity_id' => $activity->id,
                ]);
            }

            DB::commit();

            Log::info('RunnerItems: Marked as exception', [
                'request_id' => $partsRequest->id,
                'reason' => $request->input('reason'),
                'runner_id' => $user->id,
            ]);

            $partsRequest->refresh();
            $partsRequest->load(['status', 'photos']);

            return response()->json([
                'message' => 'Item marked as exception.',
                'data' => $this->formatItemForRunner($partsRequest),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('RunnerItems: Failed to mark exception', [
                'request_id' => $partsRequest->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to mark as exception.',
            ], 500);
        }
    }

    /**
     * Check if user can access an item.
     */
    protected function canAccessItem($user, PartsRequest $item): bool
    {
        // Item must be assigned to a run the user is running
        if (!$item->run_instance_id) {
            return false;
        }

        $run = $item->runInstance;
        return $run && $run->assigned_runner_user_id === $user->id;
    }

    /**
     * Get valid status transitions for runner workflow.
     */
    protected function getValidTransitions(?string $currentStatus): array
    {
        $transitions = [
            'pending' => ['assigned', 'in_transit', 'picked_up'],
            'assigned' => ['in_transit', 'picked_up'],
            'in_transit' => ['picked_up', 'delivered', 'exception'],
            'picked_up' => ['in_transit', 'delivered', 'exception'],
            'ready_to_transfer' => ['picked_up', 'in_transit'],
            'at_hub' => ['in_transit', 'picked_up'],
        ];

        return $transitions[$currentStatus] ?? ['exception'];
    }

    /**
     * Format an item for runner display.
     */
    protected function formatItemForRunner(PartsRequest $item, ?int $contextStopId = null): array
    {
        $isPickupAtStop = $contextStopId && $item->pickup_stop_id === $contextStopId;
        $isDropoffAtStop = $contextStopId && $item->dropoff_stop_id === $contextStopId;

        $completedStatuses = ['delivered', 'cancelled', 'exception'];
        $isCompleted = in_array($item->status?->name, $completedStatuses);

        return [
            'id' => $item->id,
            'reference_number' => $item->reference_number,
            'status' => [
                'id' => $item->status?->id,
                'name' => $item->status?->name,
                'display_name' => $item->status?->display_name ?? $item->status?->name,
                'color' => $item->status?->color ?? 'grey',
            ],
            'urgency' => $item->urgency ? [
                'id' => $item->urgency->id,
                'name' => $item->urgency->name,
                'color' => $item->urgency->color ?? 'grey',
            ] : null,
            'details' => $item->details,
            'special_instructions' => $item->special_instructions,
            'origin' => [
                'location_id' => $item->origin_location_id,
                'location_name' => $item->originLocation?->name
                    ?? $item->vendor?->name
                    ?? $item->vendor_name
                    ?? 'N/A',
                'vendor_id' => $item->vendor_id,
                'vendor_name' => $item->vendor?->name ?? $item->vendor_name,
                'stop_id' => $item->pickup_stop_id,
                'stop_name' => $item->pickupStop?->location?->name,
            ],
            'destination' => [
                'location_id' => $item->receiving_location_id,
                'location_name' => $item->receivingLocation?->name,
                'stop_id' => $item->dropoff_stop_id,
                'stop_name' => $item->dropoffStop?->location?->name,
            ],
            'action_at_stop' => $contextStopId ? ($isPickupAtStop ? 'pickup' : ($isDropoffAtStop ? 'dropoff' : null)) : null,
            'is_completed' => $isCompleted,
            'has_pickup_photo' => $item->hasPickupPhoto(),
            'has_delivery_photo' => $item->hasDeliveryPhoto(),
            'photos' => $item->photos->map(fn($p) => [
                'id' => $p->id,
                'type' => $p->type,
                'url' => Storage::url($p->file_path),
                'taken_at' => $p->taken_at,
            ])->toArray(),
            'valid_transitions' => $isCompleted ? [] : $this->getValidTransitions($item->status?->name),
            // Additional details - loaded when viewing single item
            'line_items' => $item->relationLoaded('items') ? $item->items->map(fn($i) => [
                'id' => $i->id,
                'description' => $i->description,
                'quantity' => $i->quantity,
                'part_number' => $i->part_number,
                'notes' => $i->notes,
                'is_verified' => $i->is_verified,
            ])->toArray() : null,
            'documents' => $item->relationLoaded('documents') ? $item->documents->map(fn($d) => [
                'id' => $d->id,
                'original_filename' => $d->original_filename,
                'description' => $d->description,
                'url' => $d->url,
                'mime_type' => $d->mime_type,
                'formatted_file_size' => $d->formatted_file_size,
                'icon' => $d->getIconName(),
                'is_previewable' => $d->isPreviewable(),
            ])->toArray() : null,
            'notes' => $item->relationLoaded('notes') ? $item->notes->map(fn($n) => [
                'id' => $n->id,
                'content' => $n->content,
                'user_name' => $n->user?->name ?? 'Unknown',
                'created_at' => $n->created_at?->toIso8601String(),
                'is_edited' => $n->is_edited,
            ])->toArray() : null,
            'line_items_count' => $item->relationLoaded('items') ? $item->items->count() : 0,
            'documents_count' => $item->relationLoaded('documents') ? $item->documents->count() : 0,
            'notes_count' => $item->relationLoaded('notes') ? $item->notes->count() : 0,
        ];
    }
}
