<?php

namespace App\Http\Controllers;

use App\Models\PartsRequest;
use App\Models\PartsRequestDocument;
use App\Models\PartsRequestEvent;
use App\Models\PartsRequestImage;
use App\Models\PartsRequestItem;
use App\Models\PartsRequestNote;
use App\Models\PartsRequestPhoto;
use App\Models\PartsRequestLocation;
use App\Models\PartsRequestStatus;
use App\Models\PartsRequestType;
use App\Services\GeofenceService;
use App\Services\SlackNotificationService;
use App\Services\RequestWorkflowService;
use App\Services\RunSchedulerService;
use App\Services\RouteGraphService;
use App\Services\InventoryIntegrationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PartsRequestController extends Controller
{
    protected $geofenceService;
    protected $slackService;
    protected $workflowService;
    protected $schedulerService;
    protected $inventoryService;
    protected $routeGraphService;

    public function __construct(
        GeofenceService $geofenceService,
        SlackNotificationService $slackService,
        RequestWorkflowService $workflowService,
        RunSchedulerService $schedulerService,
        InventoryIntegrationService $inventoryService,
        RouteGraphService $routeGraphService
    ) {
        $this->geofenceService = $geofenceService;
        $this->slackService = $slackService;
        $this->workflowService = $workflowService;
        $this->schedulerService = $schedulerService;
        $this->inventoryService = $inventoryService;
        $this->routeGraphService = $routeGraphService;
    }

    /**
     * List parts requests (dispatcher view or runner's assigned jobs)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = PartsRequest::with([
            'requestType',
            'status',
            'urgency',
            'originLocation',
            'receivingLocation',
            'requestedBy',
            'assignedRunner',
            'runInstance.route',
            'runInstance.schedule',
            'vendor',
            'customer',
            'customerAddress',
            'items',
        ])->orderBy('requested_at', 'desc');

        // All users can view all parts requests (no filtering by user)

        // Filters
        if ($request->has('status')) {
            $query->where('status_id', $request->status);
        }

        if ($request->has('urgency')) {
            $query->where('urgency_id', $request->urgency);
        }

        if ($request->has('assigned_runner')) {
            $query->where('assigned_runner_user_id', $request->assigned_runner);
        }

        if ($request->has('unassigned') && $request->unassigned === 'true') {
            $query->whereNull('assigned_runner_user_id');
        }

        if ($request->has('active_only') && $request->active_only === 'true') {
            $query->active();
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        return response()->json($query->paginate($perPage));
    }

    /**
     * Get runner's assigned jobs (mobile dashboard)
     */
    public function myJobs(Request $request)
    {
        $jobs = PartsRequest::with([
            'requestType',
            'status',
            'urgency',
            'originLocation',
            'receivingLocation',
            'photos',
            'vendor',
            'customer',
            'customerAddress',
            'items',
        ])
        ->where('assigned_runner_user_id', $request->user()->id)
        ->active()
        ->orderBy('urgency_id', 'desc')
        ->orderBy('requested_at', 'asc')
        ->get();

        return response()->json($jobs);
    }

    /**
     * Get single parts request detail
     */
    public function show(Request $request, $id)
    {
        $partsRequest = PartsRequest::with([
            'requestType',
            'status',
            'urgency',
            'originLocation',
            'originArea',
            'receivingLocation',
            'receivingArea',
            'requestedBy',
            'assignedRunner',
            'lastModifiedBy',
            'vendor',
            'vendorAddress',
            'customer',
            'customerAddress',
            'items.verifiedBy',
        ])->findOrFail($id);

        // All authenticated users can view parts requests

        return response()->json($partsRequest);
    }

    /**
     * Create new parts request
     */
    public function store(Request $request)
    {
        Gate::authorize('create', PartsRequest::class);

        $validated = $request->validate([
            'request_type_id' => 'required|exists:parts_request_types,id',
            'vendor_name' => 'nullable|string|max:255',
            'vendor_id' => 'nullable|exists:vendors,id',
            'vendor_address_id' => 'nullable|exists:addresses,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_address_id' => 'nullable|exists:addresses,id',
            'customer_lat' => 'nullable|numeric',
            'customer_lng' => 'nullable|numeric',
            'origin_location_id' => 'nullable|exists:service_locations,id',
            'origin_area_id' => 'nullable|exists:location_areas,id',
            'origin_address' => 'nullable|string',
            'origin_lat' => 'nullable|numeric',
            'origin_lng' => 'nullable|numeric',
            'receiving_location_id' => 'nullable|exists:service_locations,id',
            'receiving_area_id' => 'nullable|exists:location_areas,id',
            'urgency_id' => 'required|exists:urgency_levels,id',
            'details' => 'required|string',
            'special_instructions' => 'nullable|string',
            'not_before_datetime' => 'nullable|date',
            'slack_notify_pickup' => 'boolean',
            'slack_notify_delivery' => 'boolean',
            'slack_channel' => 'nullable|string|max:50',
            // Line items
            'items' => 'nullable|array',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Get "new" status
            $newStatus = PartsRequestStatus::where('name', 'new')->first();

            $validated['reference_number'] = PartsRequest::generateReferenceNumber();
            $validated['status_id'] = $newStatus->id;
            $validated['requested_at'] = now();
            $validated['requested_by_user_id'] = $request->user()->id;

            // Extract items before creating request
            $items = $validated['items'] ?? [];
            unset($validated['items']);

            $partsRequest = PartsRequest::create($validated);

            // Create line items if provided
            if (!empty($items)) {
                foreach ($items as $index => $itemData) {
                    PartsRequestItem::create([
                        'parts_request_id' => $partsRequest->id,
                        'description' => $itemData['description'],
                        'quantity' => $itemData['quantity'],
                        'part_number' => $itemData['part_number'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                        'sort_order' => $index,
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ]);
                }
            }

            // Create "created" event
            PartsRequestEvent::create([
                'parts_request_id' => $partsRequest->id,
                'event_type' => 'created',
                'event_at' => now(),
                'user_id' => $request->user()->id,
            ]);

            DB::commit();

            // Auto-assign to next available run (for pickup, return, transfer - not delivery)
            $autoAssigned = false;
            $autoAssignMessage = null;
            if ($this->shouldAutoAssign($partsRequest)) {
                try {
                    $autoAssigned = $this->schedulerService->autoAssignRequest($partsRequest);
                    if ($autoAssigned) {
                        $partsRequest->refresh();

                        // For transfers, set status to "confirmed"
                        $requestType = PartsRequestType::find($partsRequest->request_type_id);
                        if ($requestType && $requestType->name === 'transfer') {
                            $confirmedStatus = PartsRequestStatus::where('name', 'confirmed')->first();
                            if ($confirmedStatus) {
                                $partsRequest->update(['status_id' => $confirmedStatus->id]);
                                $partsRequest->refresh();
                            }
                        }

                        $autoAssignMessage = 'Auto-assigned to run #' . $partsRequest->run_instance_id;
                    } else {
                        $autoAssignMessage = 'Auto-assignment failed - no route found. Manual assignment required.';
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Auto-assignment failed for request #{$partsRequest->id}: " . $e->getMessage());
                    $autoAssignMessage = 'Auto-assignment failed: ' . $e->getMessage();
                }
            }

            $responseData = [
                'parts_request' => $partsRequest->load([
                    'requestType', 'status', 'urgency',
                    'originLocation', 'receivingLocation', 'requestedBy',
                    'vendor', 'vendorAddress',
                    'customer', 'customerAddress',
                    'items', 'runInstance', 'pickupStop', 'dropoffStop'
                ]),
                'message' => 'Parts request created successfully',
            ];

            if ($autoAssignMessage) {
                $responseData['auto_assign_result'] = [
                    'success' => $autoAssigned,
                    'message' => $autoAssignMessage,
                ];
            }

            return response()->json($responseData, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create parts request', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update parts request
     */
    public function update(Request $request, $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        Gate::authorize('update', $partsRequest);

        $validated = $request->validate([
            'vendor_name' => 'nullable|string|max:255',
            'vendor_id' => 'nullable|exists:vendors,id',
            'vendor_address_id' => 'nullable|exists:addresses,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_address_id' => 'nullable|exists:addresses,id',
            'origin_location_id' => 'nullable|exists:service_locations,id',
            'origin_area_id' => 'nullable|exists:location_areas,id',
            'receiving_location_id' => 'nullable|exists:service_locations,id',
            'receiving_area_id' => 'nullable|exists:location_areas,id',
            'urgency_id' => 'sometimes|exists:urgency_levels,id',
            'details' => 'sometimes|string',
            'special_instructions' => 'nullable|string',
            'not_before_datetime' => 'nullable|date',
            'slack_notify_pickup' => 'boolean',
            'slack_notify_delivery' => 'boolean',
            'slack_channel' => 'nullable|string|max:50',
        ]);

        $validated['last_modified_by_user_id'] = $request->user()->id;
        $validated['last_modified_at'] = now();

        $partsRequest->update($validated);

        return response()->json([
            'parts_request' => $partsRequest->load(['requestType', 'status', 'urgency', 'vendor', 'vendorAddress', 'customer', 'customerAddress']),
            'message' => 'Parts request updated successfully',
        ]);
    }

    /**
     * Assign runner to parts request
     */
    public function assign(Request $request, $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        Gate::authorize('assign', $partsRequest);

        $validated = $request->validate([
            'assigned_runner_user_id' => 'required|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $partsRequest->update([
                'assigned_runner_user_id' => $validated['assigned_runner_user_id'],
                'assigned_at' => now(),
                'last_modified_by_user_id' => $request->user()->id,
                'last_modified_at' => now(),
            ]);

            // Update status to "assigned"
            $assignedStatus = PartsRequestStatus::where('name', 'assigned')->first();
            $partsRequest->update(['status_id' => $assignedStatus->id]);

            // Create event
            PartsRequestEvent::create([
                'parts_request_id' => $partsRequest->id,
                'event_type' => 'assigned',
                'event_at' => now(),
                'user_id' => $request->user()->id,
                'notes' => "Assigned to " . $partsRequest->assignedRunner->name,
            ]);

            DB::commit();

            return response()->json([
                'parts_request' => $partsRequest->load(['assignedRunner', 'status']),
                'message' => 'Runner assigned successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to assign runner', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Unassign runner from parts request
     */
    public function unassign(Request $request, $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        Gate::authorize('assign', $partsRequest);

        DB::beginTransaction();
        try {
            $oldRunner = $partsRequest->assignedRunner;

            $partsRequest->update([
                'assigned_runner_user_id' => null,
                'assigned_at' => null,
                'last_modified_by_user_id' => $request->user()->id,
                'last_modified_at' => now(),
            ]);

            // Update status back to "new"
            $newStatus = PartsRequestStatus::where('name', 'new')->first();
            $partsRequest->update(['status_id' => $newStatus->id]);

            // Create event
            PartsRequestEvent::create([
                'parts_request_id' => $partsRequest->id,
                'event_type' => 'unassigned',
                'event_at' => now(),
                'user_id' => $request->user()->id,
                'notes' => "Unassigned from " . ($oldRunner->name ?? 'unknown'),
            ]);

            DB::commit();

            return response()->json([
                'parts_request' => $partsRequest->load('status'),
                'message' => 'Runner unassigned successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to unassign runner'], 500);
        }
    }

    /**
     * Add event to parts request timeline
     */
    public function addEvent(Request $request, $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        // Check authorization
        if (!$partsRequest->canBeModifiedBy($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'event_type' => 'required|in:started,arrived_pickup,picked_up,departed_pickup,arrived_dropoff,delivered,canceled,problem_reported,note_added',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Create event
            $event = PartsRequestEvent::create([
                'parts_request_id' => $partsRequest->id,
                'event_type' => $validated['event_type'],
                'event_at' => now(),
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Update status based on event type
            $statusMap = [
                'started' => 'en_route_pickup',
                'arrived_pickup' => 'en_route_pickup',
                'picked_up' => 'picked_up',
                'departed_pickup' => 'en_route_dropoff',
                'arrived_dropoff' => 'en_route_dropoff',
                'delivered' => 'delivered',
                'canceled' => 'canceled',
                'problem_reported' => 'problem',
            ];

            if (isset($statusMap[$validated['event_type']])) {
                $status = PartsRequestStatus::where('name', $statusMap[$validated['event_type']])->first();
                if ($status) {
                    $partsRequest->update(['status_id' => $status->id]);
                }
            }

            // Send Slack notifications for key events
            if ($validated['event_type'] === 'picked_up' && $partsRequest->slack_notify_pickup) {
                $this->slackService->notifyPickup($partsRequest);
            }

            if ($validated['event_type'] === 'delivered' && $partsRequest->slack_notify_delivery) {
                $this->slackService->notifyDelivery($partsRequest);
            }

            DB::commit();

            return response()->json([
                'event' => $event->load('user'),
                'parts_request' => $partsRequest->fresh(['status']),
                'message' => 'Event added successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add event', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get event timeline for parts request
     */
    public function timeline(Request $request, $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        // All authenticated users can view timeline

        $events = $partsRequest->events()->with('user')->get();

        return response()->json($events);
    }

    /**
     * Upload photo for parts request
     */
    public function uploadPhoto(Request $request, $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        // Only assigned runner can upload photos
        if (!$partsRequest->isAssignedTo($request->user())) {
            return response()->json(['message' => 'Only assigned runner can upload photos'], 403);
        }

        $validated = $request->validate([
            'stage' => 'required|in:pickup,delivery,other',
            'file' => 'required|image|max:10240', // 10MB max
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store('parts-requests/' . $partsRequest->id, 'public');

            $photo = PartsRequestPhoto::create([
                'parts_request_id' => $partsRequest->id,
                'stage' => $validated['stage'],
                'file_path' => $path,
                'taken_at' => now(),
                'taken_by_user_id' => $request->user()->id,
                'lat' => $validated['lat'] ?? null,
                'lng' => $validated['lng'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Auto-create event based on stage
            if ($validated['stage'] === 'pickup') {
                PartsRequestEvent::create([
                    'parts_request_id' => $partsRequest->id,
                    'event_type' => 'picked_up',
                    'event_at' => now(),
                    'user_id' => $request->user()->id,
                    'notes' => 'Pickup photo uploaded',
                ]);

                // Update status
                $status = PartsRequestStatus::where('name', 'picked_up')->first();
                $partsRequest->update(['status_id' => $status->id]);

                // Send Slack notification
                if ($partsRequest->slack_notify_pickup) {
                    $this->slackService->notifyPickup($partsRequest);
                }
            }

            if ($validated['stage'] === 'delivery') {
                PartsRequestEvent::create([
                    'parts_request_id' => $partsRequest->id,
                    'event_type' => 'delivered',
                    'event_at' => now(),
                    'user_id' => $request->user()->id,
                    'notes' => 'Delivery photo uploaded',
                ]);

                // Update status
                $status = PartsRequestStatus::where('name', 'delivered')->first();
                $partsRequest->update(['status_id' => $status->id]);

                // Send Slack notification
                if ($partsRequest->slack_notify_delivery) {
                    $this->slackService->notifyDelivery($partsRequest);
                }
            }

            return response()->json([
                'photo' => $photo,
                'url' => Storage::url($path),
                'message' => 'Photo uploaded successfully',
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upload photo', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get photos for parts request
     */
    public function photos(Request $request, $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        // All authenticated users can view photos

        $photos = $partsRequest->photos()->with('takenBy')->get()->map(function($photo) {
            return [
                'id' => $photo->id,
                'stage' => $photo->stage,
                'url' => $photo->url,
                'taken_at' => $photo->taken_at,
                'taken_by' => $photo->takenBy->name,
                'lat' => $photo->lat,
                'lng' => $photo->lng,
                'notes' => $photo->notes,
            ];
        });

        return response()->json($photos);
    }

    /**
     * Post GPS location (breadcrumb)
     */
    public function postLocation(Request $request, $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        // Only assigned runner can post location
        if (!$partsRequest->isAssignedTo($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'accuracy_m' => 'nullable|numeric',
            'speed_mps' => 'nullable|numeric',
            'source' => 'nullable|in:gps,manual,network',
        ]);

        // Save location
        $location = PartsRequestLocation::create([
            'parts_request_id' => $partsRequest->id,
            'runner_user_id' => $request->user()->id,
            'captured_at' => now(),
            'lat' => $validated['lat'],
            'lng' => $validated['lng'],
            'accuracy_m' => $validated['accuracy_m'] ?? null,
            'speed_mps' => $validated['speed_mps'] ?? null,
            'source' => $validated['source'] ?? 'gps',
        ]);

        // Check geofences
        $this->geofenceService->checkGeofences($partsRequest, $validated['lat'], $validated['lng']);

        return response()->json([
            'location' => $location,
            'message' => 'Location recorded successfully',
        ]);
    }

    /**
     * Get location history/tracking for parts request
     */
    public function tracking(Request $request, $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        // Check authorization
        $user = $request->user();
        if (!$user->hasDispatchAccess() && !$partsRequest->isAssignedTo($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $limit = $request->get('limit', 50);
        $locations = $partsRequest->locations()
            ->with('runner')
            ->limit($limit)
            ->get();

        return response()->json($locations);
    }

    /**
     * Delete parts request (soft delete)
     */
    public function destroy(Request $request, $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        Gate::authorize('delete', $partsRequest);

        $partsRequest->delete();

        return response()->json(['message' => 'Parts request deleted successfully']);
    }

    /**
     * Get lookup data for dropdowns
     */
    public function lookups()
    {
        return response()->json([
            'request_types' => \App\Models\PartsRequestType::all(),
            'statuses' => \App\Models\PartsRequestStatus::all(),
            'urgency_levels' => \App\Models\UrgencyLevel::all(),
        ]);
    }

    // ==========================================
    // PARTS RUNNER ROUTING ENDPOINTS (NEW)
    // ==========================================

    /**
     * POST /parts-requests/{id}/actions/{action} - Execute action
     */
    public function executeAction(Request $request, int $id, string $action)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        $validated = $request->validate([
            'note' => 'nullable|string',
            'photo' => 'nullable|file|image|max:10240',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        try {
            $this->workflowService->executeAction(
                $partsRequest,
                $action,
                $validated,
                $request->user()
            );

            return response()->json([
                'message' => 'Action executed successfully',
                'data' => $partsRequest->fresh(['status', 'runInstance', 'pickupStop', 'dropoffStop']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to execute action',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /parts-requests/{id}/available-actions - Get actions for current user/status
     */
    public function availableActions(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        $actions = $this->workflowService->getAvailableActions(
            $partsRequest,
            $request->user()
        );

        return response()->json([
            'data' => $actions,
        ]);
    }

    /**
     * POST /parts-requests/{id}/assign-to-run - Manual run assignment (dispatcher only)
     */
    public function assignToRun(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        Gate::authorize('assign', $partsRequest);

        $validated = $request->validate([
            'run_instance_id' => 'required|exists:run_instances,id',
            'pickup_stop_id' => 'required|exists:route_stops,id',
            'dropoff_stop_id' => 'required|exists:route_stops,id',
            'override_reason' => 'nullable|string',
        ]);

        try {
            // If override_reason provided, mark as admin override
            if (!empty($validated['override_reason'])) {
                $partsRequest->update([
                    'override_run_instance_id' => $validated['run_instance_id'],
                    'override_reason' => $validated['override_reason'],
                    'override_by_user_id' => $request->user()->id,
                    'override_at' => now(),
                ]);
            }

            $this->schedulerService->assignRequestToRun(
                $partsRequest,
                $validated['run_instance_id'],
                $validated['pickup_stop_id'],
                $validated['dropoff_stop_id']
            );

            return response()->json([
                'message' => 'Request assigned to run successfully',
                'data' => $partsRequest->fresh(['runInstance', 'pickupStop', 'dropoffStop']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign to run',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /parts-requests/{id}/segments - View child segments (multi-leg)
     */
    public function segments(int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        $segments = $partsRequest->childSegments()
            ->with([
                'status',
                'originLocation',
                'receivingLocation',
                'runInstance.route',
                'pickupStop',
                'dropoffStop',
            ])
            ->get();

        return response()->json([
            'data' => $segments,
        ]);
    }

    /**
     * GET /parts-requests/needs-staging - Shop staff view
     */
    public function needsStaging(Request $request)
    {
        $locationId = $request->input('location_id');

        $query = PartsRequest::with([
            'requestType',
            'status',
            'urgency',
            'originLocation',
            'receivingLocation',
            'requestedBy',
            'vendor',
        ])
        ->whereHas('requestType', function ($q) {
            $q->where('name', 'transfer');
        })
        ->whereHas('status', function ($q) {
            $q->where('name', 'new');
        });

        if ($locationId) {
            $query->where('origin_location_id', $locationId);
        }

        $requests = $query->orderBy('urgency_id', 'desc')
            ->orderBy('requested_at', 'asc')
            ->get();

        return response()->json([
            'data' => $requests,
        ]);
    }

    /**
     * GET /parts-requests/feed - Feed dashboard with advanced filters
     */
    public function feed(Request $request)
    {
        $query = PartsRequest::with([
            'requestType',
            'status',
            'urgency',
            'originLocation',
            'receivingLocation',
            'requestedBy',
            'assignedRunner',
            'runInstance.route',
            'pickupStop',
            'dropoffStop',
            'vendor',
            'customer',
            'customerAddress',
        ])->parentsOnly(); // Don't show segments in main feed

        // Advanced filters
        if ($request->has('status_ids')) {
            $query->whereIn('status_id', $request->input('status_ids'));
        }

        if ($request->has('type_ids')) {
            $query->whereIn('request_type_id', $request->input('type_ids'));
        }

        if ($request->has('urgency_ids')) {
            $query->whereIn('urgency_id', $request->input('urgency_ids'));
        }

        if ($request->has('origin_location_id')) {
            $query->where('origin_location_id', $request->input('origin_location_id'));
        }

        if ($request->has('receiving_location_id')) {
            $query->where('receiving_location_id', $request->input('receiving_location_id'));
        }

        if ($request->has('run_id')) {
            $query->where('run_instance_id', $request->input('run_id'));
        }

        if ($request->has('scheduled_date')) {
            $query->scheduledFor($request->input('scheduled_date'));
        }

        if ($request->boolean('show_future_scheduled')) {
            // Include future scheduled (dispatcher view)
            $query->orWhere->futureScheduled();
        } else {
            // Hide future scheduled (runner view)
            $query->visibleToRunner();
        }

        if ($request->boolean('show_archived')) {
            $query->archived();
        } else {
            $query->notArchived();
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('details', 'like', "%{$search}%")
                  ->orWhere('vendor_name', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        return response()->json($query->orderBy('requested_at', 'desc')->paginate($perPage));
    }

    /**
     * POST /parts-requests/{id}/link-item - Link to inventory item
     */
    public function linkItem(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
        ]);

        try {
            $this->inventoryService->linkRequestToItem(
                $partsRequest,
                $validated['item_id']
            );

            return response()->json([
                'message' => 'Item linked successfully',
                'data' => $partsRequest->fresh('item'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to link item',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /parts-requests/scheduled - View future scheduled requests (dispatcher only)
     */
    public function scheduled(Request $request)
    {
        $query = PartsRequest::with([
            'requestType',
            'status',
            'urgency',
            'originLocation',
            'receivingLocation',
            'requestedBy',
            'vendor',
        ])->futureScheduled();

        // Group by scheduled date
        $requests = $query->orderBy('scheduled_for_date', 'asc')->get();

        $grouped = $requests->groupBy(function ($request) {
            return $request->scheduled_for_date->toDateString();
        });

        return response()->json([
            'data' => $grouped,
        ]);
    }

    /**
     * POST /parts-requests/bulk-schedule - Create multiple requests for future dates
     */
    public function bulkSchedule(Request $request)
    {
        Gate::authorize('create', PartsRequest::class);

        $validated = $request->validate([
            'requests' => 'required|array|min:1',
            'requests.*.scheduled_for_date' => 'required|date|after_or_equal:today',
            'requests.*.request_type_id' => 'required|exists:parts_request_types,id',
            'requests.*.origin_location_id' => 'required|exists:service_locations,id',
            'requests.*.receiving_location_id' => 'required|exists:service_locations,id',
            'requests.*.urgency_id' => 'required|exists:urgency_levels,id',
            'requests.*.details' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $created = [];
            $newStatus = PartsRequestStatus::where('name', 'new')->first();

            foreach ($validated['requests'] as $requestData) {
                $requestData['reference_number'] = PartsRequest::generateReferenceNumber();
                $requestData['status_id'] = $newStatus->id;
                $requestData['requested_at'] = now();
                $requestData['requested_by_user_id'] = $request->user()->id;
                $requestData['created_by'] = $request->user()->id;
                $requestData['updated_by'] = $request->user()->id;

                $partsRequest = PartsRequest::create($requestData);
                $created[] = $partsRequest;
            }

            DB::commit();

            return response()->json([
                'message' => count($created) . ' requests scheduled successfully',
                'data' => $created,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to schedule requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==========================================
    // LINE ITEMS MANAGEMENT ENDPOINTS
    // ==========================================

    /**
     * GET /parts-requests/{id}/items - Get all items for a request
     */
    public function items(int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        $items = $partsRequest->items()
            ->with('verifiedBy')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * POST /parts-requests/{id}/items - Add item to request
     */
    public function addItem(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        // Check if user can modify this request
        if (!$partsRequest->canBeModifiedBy($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'description' => 'required|string|max:500',
            'quantity' => 'required|integer|min:1',
            'part_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        // Get next sort order
        $maxOrder = $partsRequest->items()->max('sort_order') ?? -1;

        $item = PartsRequestItem::create([
            'parts_request_id' => $partsRequest->id,
            'description' => $validated['description'],
            'quantity' => $validated['quantity'],
            'part_number' => $validated['part_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'sort_order' => $maxOrder + 1,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Item added successfully',
            'data' => $item,
        ], 201);
    }

    /**
     * PUT /parts-requests/{id}/items/{itemId} - Update item
     */
    public function updateItem(Request $request, int $id, int $itemId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $item = PartsRequestItem::where('parts_request_id', $id)->findOrFail($itemId);

        // Check if user can modify this request
        if (!$partsRequest->canBeModifiedBy($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'description' => 'sometimes|required|string|max:500',
            'quantity' => 'sometimes|required|integer|min:1',
            'part_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Item updated successfully',
            'data' => $item->fresh(),
        ]);
    }

    /**
     * DELETE /parts-requests/{id}/items/{itemId} - Remove item
     */
    public function removeItem(Request $request, int $id, int $itemId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $item = PartsRequestItem::where('parts_request_id', $id)->findOrFail($itemId);

        // Check if user can modify this request
        if (!$partsRequest->canBeModifiedBy($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $item->delete();

        return response()->json([
            'message' => 'Item removed successfully',
        ]);
    }

    /**
     * POST /parts-requests/{id}/items/{itemId}/verify - Runner verifies item at pickup
     */
    public function verifyItem(Request $request, int $id, int $itemId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $item = PartsRequestItem::where('parts_request_id', $id)->findOrFail($itemId);

        // Only assigned runner can verify items
        if (!$partsRequest->isAssignedTo($request->user())) {
            return response()->json(['message' => 'Only assigned runner can verify items'], 403);
        }

        $item->verify($request->user());

        return response()->json([
            'message' => 'Item verified successfully',
            'data' => $item->fresh(['verifiedBy']),
        ]);
    }

    /**
     * POST /parts-requests/{id}/items/{itemId}/unverify - Unverify item
     */
    public function unverifyItem(Request $request, int $id, int $itemId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $item = PartsRequestItem::where('parts_request_id', $id)->findOrFail($itemId);

        // Only assigned runner can unverify items
        if (!$partsRequest->isAssignedTo($request->user())) {
            return response()->json(['message' => 'Only assigned runner can unverify items'], 403);
        }

        $item->unverify();

        return response()->json([
            'message' => 'Item unverified successfully',
            'data' => $item->fresh(),
        ]);
    }

    /**
     * PUT /parts-requests/{id}/items/reorder - Reorder items
     */
    public function reorderItems(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        // Check if user can modify this request
        if (!$partsRequest->canBeModifiedBy($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'required|integer|exists:parts_request_items,id',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['item_ids'] as $index => $itemId) {
                PartsRequestItem::where('id', $itemId)
                    ->where('parts_request_id', $id)
                    ->update(['sort_order' => $index]);
            }
            DB::commit();

            return response()->json([
                'message' => 'Items reordered successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to reorder items'], 500);
        }
    }

    // ==========================================
    // DOCUMENT MANAGEMENT ENDPOINTS
    // ==========================================

    /**
     * GET /parts-requests/{id}/documents - Get all documents for a request
     */
    public function documents(int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        $documents = $partsRequest->documents()
            ->with('uploadedBy')
            ->get();

        return response()->json([
            'data' => $documents,
        ]);
    }

    /**
     * POST /parts-requests/{id}/documents - Upload document to request
     */
    public function uploadDocument(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        // Check if user can modify this request
        if (!$partsRequest->canBeModifiedBy($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|max:20480', // 20MB max
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $storedName = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('parts-requests/' . $partsRequest->id . '/documents', $storedName, 'public');

            $document = PartsRequestDocument::create([
                'parts_request_id' => $partsRequest->id,
                'original_filename' => $originalName,
                'stored_filename' => $storedName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'description' => $validated['description'] ?? null,
                'uploaded_by_user_id' => $request->user()->id,
                'uploaded_at' => now(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Document uploaded successfully',
                'data' => $document->load('uploadedBy'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /parts-requests/{id}/documents/{documentId} - Get single document details
     */
    public function showDocument(int $id, int $documentId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $document = PartsRequestDocument::where('parts_request_id', $id)->findOrFail($documentId);

        return response()->json([
            'data' => $document->load('uploadedBy'),
        ]);
    }

    /**
     * PUT /parts-requests/{id}/documents/{documentId} - Update document description
     */
    public function updateDocument(Request $request, int $id, int $documentId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $document = PartsRequestDocument::where('parts_request_id', $id)->findOrFail($documentId);

        // Check if user can modify this request
        if (!$partsRequest->canBeModifiedBy($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
        ]);

        $document->update($validated);

        return response()->json([
            'message' => 'Document updated successfully',
            'data' => $document->fresh(),
        ]);
    }

    /**
     * DELETE /parts-requests/{id}/documents/{documentId} - Delete document
     */
    public function deleteDocument(Request $request, int $id, int $documentId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $document = PartsRequestDocument::where('parts_request_id', $id)->findOrFail($documentId);

        // Check if user can modify this request
        if (!$partsRequest->canBeModifiedBy($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Model boot method handles file deletion from storage
        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully',
        ]);
    }

    /**
     * GET /parts-requests/{id}/documents/{documentId}/download - Download or view document
     * Use ?inline=1 to view inline (for images/PDFs), otherwise forces download
     */
    public function downloadDocument(Request $request, int $id, int $documentId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $document = PartsRequestDocument::where('parts_request_id', $id)->findOrFail($documentId);

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // If inline param is set and file is previewable, serve inline
        if ($request->query('inline') && $document->isPreviewable()) {
            return response()->file(
                Storage::disk('public')->path($document->file_path),
                ['Content-Type' => $document->mime_type]
            );
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type]
        );
    }

    // ==========================================
    // IMAGE MANAGEMENT ENDPOINTS
    // ==========================================

    /**
     * GET /parts-requests/{id}/images - Get all images for a request
     * Optional ?source=requester|pickup|delivery to filter
     */
    public function images(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        $query = $partsRequest->images()->with('uploadedBy');

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        $images = $query->get();

        return response()->json([
            'data' => $images,
        ]);
    }

    /**
     * POST /parts-requests/{id}/images - Upload image to request
     * source: 'requester' (default), 'pickup', 'delivery'
     */
    public function uploadImage(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        $validated = $request->validate([
            'image' => 'required|image|max:20480', // 20MB max
            'source' => 'nullable|in:requester,pickup,delivery',
            'caption' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $source = $validated['source'] ?? 'requester';

        // Authorization check based on source
        if ($source === 'requester') {
            // Requester images can be added by anyone who can modify the request
            if (!$partsRequest->canBeModifiedBy($request->user())) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            // Pickup/delivery images can only be added by assigned runner
            if (!$partsRequest->isAssignedTo($request->user())) {
                return response()->json(['message' => 'Only assigned runner can add pickup/delivery images'], 403);
            }
        }

        try {
            $imageService = app(\App\Services\ImageProcessingService::class);
            $file = $request->file('image');

            // Process and compress the image
            $directory = 'parts-requests/' . $partsRequest->id . '/images';
            $imageData = $imageService->processAndStore($file, $directory, true);

            // Extract EXIF metadata (GPS, date taken)
            $metadata = $imageService->extractMetadata($file);

            // Create the image record
            $image = PartsRequestImage::create([
                'parts_request_id' => $partsRequest->id,
                'source' => $source,
                'original_filename' => $imageData['original_filename'],
                'stored_filename' => $imageData['stored_filename'],
                'file_path' => $imageData['file_path'],
                'thumbnail_path' => $imageData['thumbnail_path'],
                'mime_type' => $imageData['mime_type'],
                'file_size' => $imageData['file_size'],
                'original_size' => $imageData['original_size'],
                'width' => $imageData['width'],
                'height' => $imageData['height'],
                'caption' => $validated['caption'] ?? null,
                'latitude' => $validated['latitude'] ?? $metadata['latitude'],
                'longitude' => $validated['longitude'] ?? $metadata['longitude'],
                'taken_at' => $metadata['taken_at'],
                'uploaded_by_user_id' => $request->user()->id,
                'uploaded_at' => now(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'data' => $image->load('uploadedBy'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /parts-requests/{id}/images/{imageId} - Serve image file
     */
    public function showImage(int $id, int $imageId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $image = PartsRequestImage::where('parts_request_id', $id)->findOrFail($imageId);

        if (!Storage::disk('public')->exists($image->file_path)) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        return response()->file(
            Storage::disk('public')->path($image->file_path),
            ['Content-Type' => $image->mime_type]
        );
    }

    /**
     * GET /parts-requests/{id}/images/{imageId}/thumbnail - Serve thumbnail
     */
    public function showImageThumbnail(int $id, int $imageId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $image = PartsRequestImage::where('parts_request_id', $id)->findOrFail($imageId);

        $path = $image->thumbnail_path ?? $image->file_path;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        return response()->file(
            Storage::disk('public')->path($path),
            ['Content-Type' => $image->mime_type]
        );
    }

    /**
     * PUT /parts-requests/{id}/images/{imageId} - Update image caption
     */
    public function updateImage(Request $request, int $id, int $imageId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $image = PartsRequestImage::where('parts_request_id', $id)->findOrFail($imageId);

        // Check authorization based on image source
        if ($image->isRequesterImage()) {
            if (!$partsRequest->canBeModifiedBy($request->user())) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            if (!$partsRequest->isAssignedTo($request->user())) {
                return response()->json(['message' => 'Only assigned runner can update pickup/delivery images'], 403);
            }
        }

        $validated = $request->validate([
            'caption' => 'nullable|string|max:255',
        ]);

        $image->update($validated);

        return response()->json([
            'message' => 'Image updated successfully',
            'data' => $image->fresh(),
        ]);
    }

    /**
     * DELETE /parts-requests/{id}/images/{imageId} - Delete image
     */
    public function deleteImage(Request $request, int $id, int $imageId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $image = PartsRequestImage::where('parts_request_id', $id)->findOrFail($imageId);

        // Check authorization based on image source
        if ($image->isRequesterImage()) {
            if (!$partsRequest->canBeModifiedBy($request->user())) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } else {
            if (!$partsRequest->isAssignedTo($request->user())) {
                return response()->json(['message' => 'Only assigned runner can delete pickup/delivery images'], 403);
            }
        }

        // Model boot method handles file deletion from storage
        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully',
        ]);
    }

    // ==================== NOTES METHODS ====================

    /**
     * GET /parts-requests/{id}/notes - List notes for a request
     */
    public function notes(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $user = $request->user();
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin');

        $notes = $partsRequest->notes()->with('user:id,first_name,last_name,preferred_name,username')->get();

        // Add permission flags to each note
        $notes->each(function ($note) use ($user, $isAdmin) {
            $isOwner = $note->user_id === $user->id;
            $note->can_edit = $isOwner || $isAdmin;
            $note->can_delete = $isOwner || $isAdmin;
        });

        return response()->json([
            'data' => $notes,
        ]);
    }

    /**
     * POST /parts-requests/{id}/notes - Create a new note
     */
    public function storeNote(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $user = $request->user();

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $note = PartsRequestNote::create([
            'parts_request_id' => $partsRequest->id,
            'content' => $validated['content'],
            'user_id' => $user->id,
            'is_edited' => false,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $note->load('user:id,first_name,last_name,preferred_name,username');
        $note->can_edit = true;
        $note->can_delete = true;

        return response()->json([
            'message' => 'Note added successfully',
            'data' => $note,
        ], 201);
    }

    /**
     * PUT /parts-requests/{id}/notes/{noteId} - Update a note
     */
    public function updateNote(Request $request, int $id, int $noteId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $note = PartsRequestNote::where('parts_request_id', $id)->findOrFail($noteId);
        $user = $request->user();
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin');

        // Check authorization - owner or admin
        if ($note->user_id !== $user->id && !$isAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $note->update([
            'content' => $validated['content'],
            'is_edited' => true,
            'edited_at' => now(),
            'updated_by' => $user->id,
        ]);

        $note->load('user:id,first_name,last_name,preferred_name,username');
        $note->can_edit = true;
        $note->can_delete = true;

        return response()->json([
            'message' => 'Note updated successfully',
            'data' => $note,
        ]);
    }

    /**
     * DELETE /parts-requests/{id}/notes/{noteId} - Delete a note
     */
    public function deleteNote(Request $request, int $id, int $noteId)
    {
        $partsRequest = PartsRequest::findOrFail($id);
        $note = PartsRequestNote::where('parts_request_id', $id)->findOrFail($noteId);
        $user = $request->user();
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin');

        // Check authorization - owner or admin
        if ($note->user_id !== $user->id && !$isAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $note->delete();

        return response()->json([
            'message' => 'Note deleted successfully',
        ]);
    }

    // ==========================================
    // AUTO-ASSIGNMENT HELPER METHODS
    // ==========================================

    /**
     * Determine if a parts request should be auto-assigned to a run.
     * Auto-assign: pickup, return, transfer (vendor-related and shop transfers)
     * DO NOT auto-assign: delivery (customer deliveries handled by dispatch)
     */
    private function shouldAutoAssign(PartsRequest $request): bool
    {
        // Skip delivery requests (customer deliveries handled by dispatch)
        $requestType = PartsRequestType::find($request->request_type_id);
        $requestTypeName = $requestType?->name ?? '';

        if ($requestTypeName === 'delivery') {
            return false;
        }

        // For vendor pickups/returns: must have vendor_id and receiving_location_id
        if ($request->vendor_id && $request->receiving_location_id) {
            return true;
        }

        // For transfers: must have origin_location_id and receiving_location_id
        if ($request->origin_location_id && $request->receiving_location_id) {
            return true;
        }

        return false;
    }

    /**
     * POST /parts-requests/{id}/not-ready - Runner marks pickup as not ready
     * Moves the request to the next available run on the same route
     *
     * If next run is Saturday, returns saturday_prompt: true for frontend to confirm
     */
    public function markNotReady(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        // Only assigned runner can mark as not ready
        if (!$partsRequest->isAssignedTo($request->user())) {
            return response()->json(['message' => 'Only assigned runner can mark pickup as not ready'], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Use existing reassignToNextRun() method
            $reassigned = $this->schedulerService->reassignToNextRun($partsRequest);

            // Create event
            $partsRequest->events()->create([
                'event_type' => 'status_changed',
                'notes' => 'Pickup not ready, moved to next run. ' . ($validated['reason'] ?? ''),
                'event_at' => now(),
                'user_id' => $request->user()->id,
            ]);

            DB::commit();

            // Check if this is a Saturday prompt (reassigned returns array)
            if (is_array($reassigned) && !empty($reassigned['saturday_prompt'])) {
                return response()->json([
                    'message' => 'Next available run is on Saturday',
                    'saturday_prompt' => true,
                    'saturday_date' => $reassigned['date'],
                    'saturday_time' => $reassigned['time'],
                    'route_id' => $reassigned['route_id'],
                    'data' => $partsRequest->fresh(['status', 'runInstance']),
                ]);
            }

            if (!$reassigned) {
                return response()->json([
                    'message' => 'Marked not ready but no next run available',
                    'needs_manual_assignment' => true,
                    'data' => $partsRequest->fresh(['status', 'runInstance']),
                ]);
            }

            return response()->json([
                'message' => 'Moved to next run',
                'data' => $partsRequest->fresh(['status', 'runInstance', 'pickupStop', 'dropoffStop']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to mark as not ready',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /parts-requests/{id}/schedule-saturday-choice - User chooses Saturday or next business day
     * Called after frontend shows Saturday confirmation dialog
     */
    public function scheduleSaturdayChoice(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        Gate::authorize('update', $partsRequest);

        $validated = $request->validate([
            'use_saturday' => 'required|boolean',
            'saturday_date' => 'required|date',
        ]);

        $saturdayDate = Carbon::parse($validated['saturday_date']);

        DB::beginTransaction();
        try {
            if ($validated['use_saturday']) {
                // User chose Saturday - find the Saturday run and assign
                $runResult = $this->schedulerService->findNextAvailableRun(
                    $partsRequest->runInstance?->route_id ?? $this->findRouteForRequest($partsRequest),
                    $saturdayDate->copy()->startOfDay(),
                    $saturdayDate,
                    $partsRequest->origin_location_id
                );

                if (!$runResult || !$runResult['run']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Saturday run no longer available',
                        'needs_manual_assignment' => true,
                    ], 400);
                }

                $run = $runResult['run'];

                // Find stops
                $pickupStop = $run->route->stops()
                    ->where('location_id', $partsRequest->origin_location_id)
                    ->first();

                $dropoffStop = $run->route->stops()
                    ->where('location_id', $partsRequest->receiving_location_id)
                    ->first();

                if (!$pickupStop || !$dropoffStop) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Could not find appropriate stops on Saturday run',
                        'needs_manual_assignment' => true,
                    ], 400);
                }

                $this->schedulerService->assignRequestToRun(
                    $partsRequest,
                    $run->id,
                    $pickupStop->id,
                    $dropoffStop->id
                );

                // Create event
                $partsRequest->events()->create([
                    'event_type' => 'assigned',
                    'notes' => "Scheduled for Saturday ({$saturdayDate->toDateString()}) by user choice",
                    'event_at' => now(),
                    'user_id' => $request->user()->id,
                ]);
            } else {
                // User declined Saturday - find next business day
                $success = $this->schedulerService->reassignToNextBusinessDay($partsRequest, $saturdayDate);

                if (!$success) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'No run available for next business day',
                        'needs_manual_assignment' => true,
                    ], 400);
                }

                // Create event
                $partsRequest->events()->create([
                    'event_type' => 'assigned',
                    'notes' => "Scheduled for next business day (skipped Saturday {$saturdayDate->toDateString()})",
                    'event_at' => now(),
                    'user_id' => $request->user()->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => $validated['use_saturday'] ? 'Scheduled for Saturday' : 'Scheduled for next business day',
                'data' => $partsRequest->fresh(['status', 'runInstance.route', 'pickupStop', 'dropoffStop']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to schedule request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper to find a route for a request (when not yet assigned)
     */
    private function findRouteForRequest(PartsRequest $request): ?int
    {
        $path = $this->routeGraphService->findPath(
            $request->origin_location_id,
            $request->receiving_location_id
        );

        if ($path && !empty($path['routes'])) {
            return $path['routes'][0];
        }

        return null;
    }

    /**
     * POST /parts-requests/{id}/assign-to-next-run - Auto-assign to next available run
     *
     * Finds the appropriate route based on request type:
     * - Vendor pickups/returns: Route serving vendor and destination
     * - Transfers: Route connecting origin to destination
     *
     * Returns saturday_prompt: true if next run is Saturday
     */
    public function assignToNextAvailableRun(Request $request, int $id)
    {
        $partsRequest = PartsRequest::findOrFail($id);

        Gate::authorize('assign', $partsRequest);

        // Can't assign if already assigned
        if ($partsRequest->run_instance_id) {
            return response()->json([
                'message' => 'Request is already assigned to a run',
                'data' => $partsRequest->load(['runInstance.route', 'pickupStop', 'dropoffStop']),
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Use the auto-assignment logic
            $result = $this->schedulerService->autoAssignRequest($partsRequest);

            if ($result === false) {
                // No route found - need manual assignment
                DB::rollBack();
                return response()->json([
                    'message' => 'No route found for this request. Manual assignment required.',
                    'needs_manual_assignment' => true,
                ], 400);
            }

            // Check if it returned a Saturday prompt (array)
            if (is_array($result) && !empty($result['saturday_prompt'])) {
                DB::commit();
                return response()->json([
                    'message' => 'Next available run is on Saturday',
                    'saturday_prompt' => true,
                    'saturday_date' => $result['date'],
                    'saturday_time' => $result['time'] ?? null,
                    'route_id' => $result['route_id'] ?? null,
                    'data' => $partsRequest->fresh(['status', 'runInstance.route']),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Request assigned to next available run',
                'data' => $partsRequest->fresh([
                    'status',
                    'runInstance.route',
                    'runInstance.assignedRunner',
                    'pickupStop',
                    'dropoffStop',
                ]),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to assign to next run',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
