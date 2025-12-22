<?php

namespace App\Http\Controllers;

use App\Models\RunInstance;
use App\Models\RunNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RunInstanceController extends Controller
{
    /**
     * GET /runs - List runs
     */
    public function index(Request $request)
    {
        $query = RunInstance::with(['route', 'assignedRunner', 'assignedVehicle']);

        // Filter by date
        if ($request->has('date')) {
            $query->forDate($request->input('date'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->byStatus($request->input('status'));
        }

        // Filter by runner
        if ($request->has('runner_id')) {
            $query->forRunner($request->integer('runner_id'));
        }

        // Filter by route
        if ($request->has('route_id')) {
            $query->where('route_id', $request->integer('route_id'));
        }

        // Default to upcoming runs if no date filter
        if (!$request->has('date')) {
            $query->upcoming();
        }

        $runs = $query->orderBy('scheduled_date')->orderBy('scheduled_time')->get();

        return response()->json([
            'data' => $runs,
        ]);
    }

    /**
     * GET /runs/my-runs - Today's runs for authenticated runner
     */
    public function myRuns(Request $request)
    {
        $date = $request->input('date', today()->toDateString());

        $runs = RunInstance::forRunner(auth()->id())
            ->forDate($date)
            ->with([
                'route.stops.location',
                'route.stops.vendorClusterLocations.vendorLocation',
                'requests' => function ($query) use ($date) {
                    $query->visibleToRunner()
                          ->with(['requestType', 'status', 'urgency', 'originLocation', 'receivingLocation']);
                },
                'stopActuals',
                'notes',
            ])
            ->orderBy('scheduled_time')
            ->get();

        return response()->json([
            'data' => $runs,
        ]);
    }

    /**
     * GET /runs/{id} - Single run details
     */
    public function show(int $id)
    {
        $run = RunInstance::with([
            'route.stops.location',
            'route.stops.vendorClusterLocations.vendorLocation',
            'assignedRunner',
            'assignedVehicle',
            'requests.requestType',
            'requests.status',
            'requests.urgency',
            'requests.originLocation',
            'requests.receivingLocation',
            'requests.pickupStop',
            'requests.dropoffStop',
            'stopActuals.routeStop',
            'notes.createdBy',
        ])->findOrFail($id);

        // Group requests by stop for easier display
        $requestsByStop = [];
        foreach ($run->requests as $request) {
            if ($request->pickup_stop_id) {
                $requestsByStop[$request->pickup_stop_id]['pickups'][] = $request;
            }
            if ($request->dropoff_stop_id) {
                $requestsByStop[$request->dropoff_stop_id]['dropoffs'][] = $request;
            }
        }

        return response()->json([
            'data' => $run,
            'requests_by_stop' => $requestsByStop,
        ]);
    }

    /**
     * POST /runs/{id}/assign - Assign runner/vehicle
     */
    public function assign(Request $request, int $id)
    {
        $run = RunInstance::findOrFail($id);

        $validated = $request->validate([
            'assigned_runner_user_id' => 'required|exists:users,id',
            'assigned_vehicle_location_id' => 'nullable|exists:service_locations,id',
        ]);

        $validated['updated_by'] = auth()->id();

        $run->update($validated);

        Log::info("Run #{$run->id} assigned to runner #{$validated['assigned_runner_user_id']}");

        return response()->json([
            'message' => 'Run assigned successfully',
            'data' => $run->load(['assignedRunner', 'assignedVehicle']),
        ]);
    }

    /**
     * POST /runs/{id}/start - Mark run as started
     */
    public function start(int $id)
    {
        $run = RunInstance::findOrFail($id);

        // Validate user is assigned runner or has permission
        if ($run->assigned_runner_user_id !== auth()->id() && !auth()->user()->hasPermission('runs.start')) {
            return response()->json([
                'message' => 'You are not authorized to start this run',
            ], 403);
        }

        if ($run->status !== 'pending') {
            return response()->json([
                'message' => 'Run is not in pending status',
            ], 400);
        }

        $run->start();

        // Create run_stop_actuals records for each stop
        foreach ($run->route->stops as $stop) {
            $run->stopActuals()->create([
                'route_stop_id' => $stop->id,
                'tasks_total' => $run->getRequestsForStop($stop->id)->count(),
            ]);
        }

        Log::info("Run #{$run->id} started by user #" . auth()->id());

        return response()->json([
            'message' => 'Run started successfully',
            'data' => $run->fresh(),
        ]);
    }

    /**
     * POST /runs/{id}/complete - Mark run as completed
     */
    public function complete(int $id)
    {
        $run = RunInstance::findOrFail($id);

        // Validate user is assigned runner or has permission
        if ($run->assigned_runner_user_id !== auth()->id() && !auth()->user()->hasPermission('runs.complete')) {
            return response()->json([
                'message' => 'You are not authorized to complete this run',
            ], 403);
        }

        if ($run->status !== 'in_progress') {
            return response()->json([
                'message' => 'Run is not in progress',
            ], 400);
        }

        $run->complete();

        Log::info("Run #{$run->id} completed by user #" . auth()->id());

        return response()->json([
            'message' => 'Run completed successfully',
            'data' => $run->fresh(),
        ]);
    }

    /**
     * POST /runs/{id}/notes - Add operational note
     */
    public function addNote(Request $request, int $id)
    {
        $run = RunInstance::findOrFail($id);

        $validated = $request->validate([
            'note_type' => 'required|in:general,delay,issue,completion',
            'notes' => 'required|string',
        ]);

        $note = $run->notes()->create([
            'note_type' => $validated['note_type'],
            'notes' => $validated['notes'],
            'created_by_user_id' => auth()->id(),
        ]);

        Log::info("Note added to run #{$run->id} by user #" . auth()->id());

        return response()->json([
            'message' => 'Note added successfully',
            'data' => $note->load('createdBy'),
        ], 201);
    }

    /**
     * GET /runs/{id}/notes - View notes
     */
    public function getNotes(int $id)
    {
        $run = RunInstance::findOrFail($id);

        $notes = $run->notes()
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $notes,
        ]);
    }

    /**
     * PUT /runs/{id}/current-stop - Update current stop
     */
    public function updateCurrentStop(Request $request, int $id)
    {
        $run = RunInstance::findOrFail($id);

        $validated = $request->validate([
            'current_stop_id' => 'required|exists:route_stops,id',
            'arrived_at' => 'nullable|date',
        ]);

        // Validate stop belongs to this route
        $stop = $run->route->stops()->where('id', $validated['current_stop_id'])->firstOrFail();

        $run->update([
            'current_stop_id' => $validated['current_stop_id'],
            'updated_by' => auth()->id(),
        ]);

        // Update run_stop_actual if arrived_at provided
        if (!empty($validated['arrived_at'])) {
            $actual = $run->stopActuals()
                ->where('route_stop_id', $validated['current_stop_id'])
                ->first();

            if ($actual) {
                $actual->update([
                    'arrived_at' => $validated['arrived_at'],
                ]);
            }
        }

        Log::info("Run #{$run->id} current stop updated to #{$validated['current_stop_id']}");

        return response()->json([
            'message' => 'Current stop updated successfully',
            'data' => $run->fresh('currentStop'),
        ]);
    }

    /**
     * POST /runs/{id}/stops/{stopId}/arrive - Mark arrival at stop
     */
    public function arriveAtStop(Request $request, int $id, int $stopId)
    {
        $run = RunInstance::findOrFail($id);
        $stop = $run->route->stops()->where('id', $stopId)->firstOrFail();

        $actual = $run->stopActuals()
            ->where('route_stop_id', $stopId)
            ->firstOrFail();

        $actual->update([
            'arrived_at' => now(),
        ]);

        $run->update([
            'current_stop_id' => $stopId,
            'updated_by' => auth()->id(),
        ]);

        Log::info("Run #{$run->id} arrived at stop #{$stopId}");

        return response()->json([
            'message' => 'Arrival recorded successfully',
            'data' => $actual->fresh(),
        ]);
    }

    /**
     * POST /runs/{id}/stops/{stopId}/depart - Mark departure from stop
     */
    public function departFromStop(Request $request, int $id, int $stopId)
    {
        $run = RunInstance::findOrFail($id);
        $stop = $run->route->stops()->where('id', $stopId)->firstOrFail();

        $actual = $run->stopActuals()
            ->where('route_stop_id', $stopId)
            ->firstOrFail();

        // Check for incomplete tasks
        if (!$actual->allTasksCompleted() && !$request->boolean('force')) {
            return response()->json([
                'warning' => true,
                'message' => "You have {$actual->tasks_total - $actual->tasks_completed} incomplete tasks. Set 'force=true' to proceed anyway.",
                'incomplete_tasks' => $actual->tasks_total - $actual->tasks_completed,
            ], 400);
        }

        $actual->update([
            'departed_at' => now(),
        ]);

        Log::info("Run #{$run->id} departed from stop #{$stopId}");

        return response()->json([
            'message' => 'Departure recorded successfully',
            'data' => $actual->fresh(),
        ]);
    }
}
