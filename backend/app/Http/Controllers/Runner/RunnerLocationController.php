<?php

namespace App\Http\Controllers\Runner;

use App\Http\Controllers\Controller;
use App\Models\RouteStop;
use App\Models\RunInstance;
use App\Models\RunnerLocation;
use App\Services\GeofenceService;
use App\Services\RunnerAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for runner location tracking and geofence detection.
 */
class RunnerLocationController extends Controller
{
    public function __construct(
        protected GeofenceService $geofenceService,
        protected RunnerAlertService $alertService
    ) {}

    /**
     * Record runner's current location.
     * Returns nearest stop info and geofence status.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'accuracy_m' => 'nullable|integer|min:0',
            'run_id' => 'nullable|exists:run_instances,id',
        ]);

        $user = $request->user();
        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');
        $runId = $request->input('run_id');

        // Record location breadcrumb
        $location = RunnerLocation::create([
            'user_id' => $user->id,
            'run_id' => $runId,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_m' => $request->input('accuracy_m'),
            'recorded_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = [
            'recorded' => true,
            'location_id' => $location->id,
            'coordinates' => [
                'lat' => $lat,
                'lng' => $lng,
            ],
        ];

        // If a run is active, calculate stop distances
        if ($runId) {
            $nearestStop = $this->geofenceService->findNearestStop($lat, $lng, $runId);

            if ($nearestStop) {
                $response['nearest_stop'] = [
                    'stop_id' => $nearestStop['stop_id'],
                    'location_id' => $nearestStop['location_id'],
                    'location_name' => $nearestStop['location_name'],
                    'distance_m' => $nearestStop['distance_m'],
                    'inside' => $nearestStop['inside'],
                    'radius_m' => $nearestStop['radius_m'],
                ];

                // Auto-update current stop if inside
                if ($nearestStop['inside']) {
                    $run = RunInstance::find($runId);
                    if ($run && $run->assigned_runner_user_id === $user->id) {
                        if ($run->current_stop_id !== $nearestStop['stop_id']) {
                            $run->update([
                                'current_stop_id' => $nearestStop['stop_id'],
                                'updated_by' => $user->id,
                            ]);
                            $response['stop_auto_selected'] = true;
                        }
                    }
                }
            }

            // Get all stops with distances
            $stopsWithDistances = $this->geofenceService->getStopsWithDistances($lat, $lng, $runId);
            $response['all_stops'] = $stopsWithDistances->toArray();
        }

        return response()->json($response);
    }

    /**
     * Check for open items when exiting a stop.
     * Triggers alerts if there are unfinished items.
     */
    public function exitCheck(Request $request, RouteStop $stop): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'run_id' => 'required|exists:run_instances,id',
        ]);

        $user = $request->user();
        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');
        $runId = $request->input('run_id');

        $run = RunInstance::with('route')->find($runId);

        if (!$run || $run->assigned_runner_user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not assigned to this run.',
            ], 403);
        }

        // Check if actually exited the stop
        $hasExited = $this->geofenceService->hasExitedStop($lat, $lng, $stop);

        if (!$hasExited) {
            return response()->json([
                'exited' => false,
                'message' => 'Still inside stop geofence.',
            ]);
        }

        // Get open items at this stop
        $openItems = $run->requests()
            ->where(function ($q) use ($stop) {
                $q->where('pickup_stop_id', $stop->id)
                    ->orWhere('dropoff_stop_id', $stop->id);
            })
            ->whereHas('status', function ($q) {
                $q->whereNotIn('name', ['delivered', 'cancelled', 'exception']);
            })
            ->with(['status'])
            ->get();

        $response = [
            'exited' => true,
            'stop_id' => $stop->id,
            'stop_name' => $stop->location?->name ?? 'Unknown',
            'open_items_count' => $openItems->count(),
            'open_items' => $openItems->map(fn($item) => [
                'id' => $item->id,
                'reference_number' => $item->reference_number,
                'status' => $item->status?->name,
                'action_at_stop' => $item->pickup_stop_id === $stop->id ? 'pickup' : 'dropoff',
            ])->toArray(),
        ];

        // Trigger alerts if there are open items
        if ($openItems->isNotEmpty()) {
            Log::warning('RunnerLocation: Exited stop with open items', [
                'runner_id' => $user->id,
                'run_id' => $runId,
                'stop_id' => $stop->id,
                'open_count' => $openItems->count(),
            ]);

            // Send alerts via RunnerAlertService
            $this->alertService->alertLeftWithOpenItems($user, $run, $stop, $openItems);

            $response['alert_sent'] = true;
        }

        return response()->json($response);
    }

    /**
     * Get runner's recent location history.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'run_id' => 'nullable|exists:run_instances,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $limit = $request->input('limit', 20);

        $query = RunnerLocation::forUser($user->id)
            ->orderByDesc('recorded_at');

        if ($request->has('run_id')) {
            $query->forRun($request->input('run_id'));
        }

        $locations = $query->take($limit)->get();

        return response()->json([
            'data' => $locations->map(fn($loc) => [
                'id' => $loc->id,
                'lat' => (float) $loc->lat,
                'lng' => (float) $loc->lng,
                'accuracy_m' => $loc->accuracy_m,
                'recorded_at' => $loc->recorded_at->toIso8601String(),
            ])->toArray(),
        ]);
    }

    /**
     * Get current geofence status for all stops in a run.
     */
    public function geofenceStatus(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'run_id' => 'required|exists:run_instances,id',
        ]);

        $user = $request->user();
        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');
        $runId = $request->input('run_id');

        $run = RunInstance::find($runId);

        if (!$run || $run->assigned_runner_user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not assigned to this run.',
            ], 403);
        }

        $stopsWithDistances = $this->geofenceService->getStopsWithDistances($lat, $lng, $runId);

        // Find which stop (if any) the runner is inside
        $currentStop = $stopsWithDistances->first(fn($s) => $s['inside']);

        return response()->json([
            'current_position' => [
                'lat' => $lat,
                'lng' => $lng,
            ],
            'current_stop' => $currentStop ? [
                'stop_id' => $currentStop['stop_id'],
                'location_name' => $currentStop['location_name'],
            ] : null,
            'stops' => $stopsWithDistances->toArray(),
        ]);
    }
}
