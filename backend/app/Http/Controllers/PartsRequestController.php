<?php

namespace App\Http\Controllers;

use App\Models\PartsRequest;
use App\Models\PartsRequestEvent;
use App\Models\PartsRequestPhoto;
use App\Models\PartsRequestLocation;
use App\Models\PartsRequestStatus;
use App\Services\GeofenceService;
use App\Services\SlackNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PartsRequestController extends Controller
{
    protected $geofenceService;
    protected $slackService;

    public function __construct(GeofenceService $geofenceService, SlackNotificationService $slackService)
    {
        $this->geofenceService = $geofenceService;
        $this->slackService = $slackService;
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
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
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
            'pickup_run' => 'boolean',
            'slack_notify_pickup' => 'boolean',
            'slack_notify_delivery' => 'boolean',
            'slack_channel' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();
        try {
            // Get "new" status
            $newStatus = PartsRequestStatus::where('name', 'new')->first();

            $validated['reference_number'] = PartsRequest::generateReferenceNumber();
            $validated['status_id'] = $newStatus->id;
            $validated['requested_at'] = now();
            $validated['requested_by_user_id'] = $request->user()->id;

            $partsRequest = PartsRequest::create($validated);

            // Create "created" event
            PartsRequestEvent::create([
                'parts_request_id' => $partsRequest->id,
                'event_type' => 'created',
                'event_at' => now(),
                'user_id' => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'parts_request' => $partsRequest->load([
                    'requestType', 'status', 'urgency',
                    'originLocation', 'receivingLocation', 'requestedBy'
                ]),
                'message' => 'Parts request created successfully',
            ], 201);

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
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'origin_location_id' => 'nullable|exists:service_locations,id',
            'origin_area_id' => 'nullable|exists:location_areas,id',
            'receiving_location_id' => 'nullable|exists:service_locations,id',
            'receiving_area_id' => 'nullable|exists:location_areas,id',
            'urgency_id' => 'sometimes|exists:urgency_levels,id',
            'details' => 'sometimes|string',
            'special_instructions' => 'nullable|string',
            'slack_notify_pickup' => 'boolean',
            'slack_notify_delivery' => 'boolean',
            'slack_channel' => 'nullable|string|max:50',
        ]);

        $validated['last_modified_by_user_id'] = $request->user()->id;
        $validated['last_modified_at'] = now();

        $partsRequest->update($validated);

        return response()->json([
            'parts_request' => $partsRequest->load(['requestType', 'status', 'urgency']),
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
}
