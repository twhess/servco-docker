<?php

namespace App\Http\Controllers\Runner;

use App\Http\Controllers\Controller;
use App\Models\RunInstance;
use App\Models\RunnerVehicleSession;
use App\Models\ServiceLocation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller for runner run management.
 */
class RunnerRunsController extends Controller
{
    /**
     * List runs available to the runner (assigned + claimable).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->input('date', Carbon::today()->toDateString());

        // Get runs assigned to this runner for today
        $assignedRuns = RunInstance::with([
            'route.stops.location',
            'assignedVehicle',
        ])
            ->forRunner($user->id)
            ->forDate($date)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('scheduled_time')
            ->get()
            ->map(fn($run) => $this->formatRunForRunner($run));

        // Get unassigned runs for today that the runner can claim
        $availableRuns = RunInstance::with([
            'route.stops.location',
        ])
            ->forDate($date)
            ->whereNull('assigned_runner_user_id')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('scheduled_time')
            ->get()
            ->map(fn($run) => $this->formatRunForRunner($run));

        return response()->json([
            'assigned' => $assignedRuns,
            'available' => $availableRuns,
            'date' => $date,
        ]);
    }

    /**
     * Get details of a specific run.
     */
    public function show(Request $request, RunInstance $run): JsonResponse
    {
        $user = $request->user();

        // Ensure runner can view this run
        if ($run->assigned_runner_user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not assigned to this run.',
            ], 403);
        }

        $run->load([
            'route.stops.location',
            'assignedVehicle',
            'requests.status',
            'requests.urgency',
            'requests.originLocation',
            'requests.receivingLocation',
            'requests.pickupStop',
            'requests.dropoffStop',
            'requests.vendor',
        ]);

        // Build stops with item counts
        $stops = $run->route->stops->map(function ($stop) use ($run) {
            $requests = $run->requests->filter(function ($request) use ($stop) {
                return $request->pickup_stop_id === $stop->id
                    || $request->dropoff_stop_id === $stop->id;
            });

            $openRequests = $requests->filter(function ($request) {
                $completedStatuses = ['delivered', 'cancelled', 'exception'];
                return !in_array($request->status?->name, $completedStatuses);
            });

            // Get pickup vendors for this stop (unique vendor names)
            $pickupVendors = $requests
                ->filter(fn($r) => $r->pickup_stop_id === $stop->id)
                ->map(fn($r) => $r->vendor?->name ?? $r->vendor_name)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // Determine location name - use location name if set, otherwise build from vendors
            $locationName = $stop->location?->name;
            if (empty($locationName) || $locationName === 'Unknown') {
                if (count($pickupVendors) > 0) {
                    $locationName = count($pickupVendors) === 1
                        ? $pickupVendors[0]
                        : implode(', ', array_slice($pickupVendors, 0, 2)) . (count($pickupVendors) > 2 ? '...' : '');
                } else {
                    $locationName = 'Stop #' . $stop->stop_order;
                }
            }

            return [
                'id' => $stop->id,
                'location_id' => $stop->location_id,
                'location_name' => $locationName,
                'stop_order' => $stop->stop_order,
                'latitude' => $stop->location?->latitude,
                'longitude' => $stop->location?->longitude,
                'geofence_radius_m' => $stop->location?->geofence_radius_m ?? 200,
                'total_items' => $requests->count(),
                'open_items' => $openRequests->count(),
                'completed_items' => $requests->count() - $openRequests->count(),
                'pickup_vendors' => $pickupVendors,
            ];
        })->sortBy('stop_order')->values();

        return response()->json([
            'run' => [
                'id' => $run->id,
                'display_name' => $run->display_name,
                'route_id' => $run->route_id,
                'route_name' => $run->route?->name,
                'scheduled_date' => $run->scheduled_date?->toDateString(),
                'scheduled_time' => $run->scheduled_time?->format('H:i'),
                'status' => $run->status,
                'actual_start_at' => $run->actual_start_at,
                'current_stop_id' => $run->current_stop_id,
                'vehicle' => $run->assignedVehicle ? [
                    'id' => $run->assignedVehicle->id,
                    'name' => $run->assignedVehicle->name,
                ] : null,
            ],
            'stops' => $stops,
            'summary' => [
                'total_stops' => $stops->count(),
                'total_items' => $run->requests->count(),
                'open_items' => $run->requests->filter(function ($r) {
                    return !in_array($r->status?->name, ['delivered', 'cancelled', 'exception']);
                })->count(),
            ],
        ]);
    }

    /**
     * Claim an unassigned run.
     */
    public function claim(Request $request, RunInstance $run): JsonResponse
    {
        $user = $request->user();

        // Check if run can be claimed
        if ($run->assigned_runner_user_id !== null) {
            return response()->json([
                'message' => 'This run has already been claimed.',
            ], 422);
        }

        if (!in_array($run->status, ['pending', 'in_progress'])) {
            return response()->json([
                'message' => 'This run cannot be claimed.',
            ], 422);
        }

        // Wrap in transaction
        DB::beginTransaction();
        try {
            $run->update([
                'assigned_runner_user_id' => $user->id,
                'updated_by' => $user->id,
            ]);

            DB::commit();

            Log::info('RunnerRuns: Run claimed', [
                'run_id' => $run->id,
                'runner_id' => $user->id,
            ]);

            $run->load(['route.stops.location']);

            return response()->json([
                'message' => 'Run claimed successfully.',
                'run' => $this->formatRunForRunner($run),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('RunnerRuns: Failed to claim run', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to claim run. Please try again.',
            ], 500);
        }
    }

    /**
     * Start a run (mark as in_progress).
     */
    public function start(Request $request, RunInstance $run): JsonResponse
    {
        $user = $request->user();

        if ($run->assigned_runner_user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not assigned to this run.',
            ], 403);
        }

        if ($run->status !== 'pending') {
            return response()->json([
                'message' => 'Run has already been started.',
            ], 422);
        }

        $run->start();

        Log::info('RunnerRuns: Run started', [
            'run_id' => $run->id,
            'runner_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Run started.',
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'actual_start_at' => $run->actual_start_at,
            ],
        ]);
    }

    /**
     * Complete a run.
     */
    public function complete(Request $request, RunInstance $run): JsonResponse
    {
        $user = $request->user();

        if ($run->assigned_runner_user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not assigned to this run.',
            ], 403);
        }

        if ($run->status !== 'in_progress') {
            return response()->json([
                'message' => 'Run is not in progress.',
            ], 422);
        }

        // Check for open items
        $openItems = $run->requests()
            ->whereHas('status', function ($q) {
                $q->whereNotIn('name', ['delivered', 'cancelled', 'exception']);
            })
            ->count();

        if ($openItems > 0 && !$request->input('force', false)) {
            return response()->json([
                'message' => "Cannot complete run with {$openItems} open item(s).",
                'open_items' => $openItems,
                'force_available' => true,
            ], 422);
        }

        $run->complete();

        Log::info('RunnerRuns: Run completed', [
            'run_id' => $run->id,
            'runner_id' => $user->id,
            'forced' => $request->input('force', false),
        ]);

        return response()->json([
            'message' => 'Run completed.',
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'actual_end_at' => $run->actual_end_at,
            ],
        ]);
    }

    /**
     * Update current stop (manual override).
     */
    public function updateCurrentStop(Request $request, RunInstance $run): JsonResponse
    {
        $request->validate([
            'stop_id' => 'required|exists:route_stops,id',
        ]);

        $user = $request->user();

        if ($run->assigned_runner_user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not assigned to this run.',
            ], 403);
        }

        $run->update([
            'current_stop_id' => $request->input('stop_id'),
            'updated_by' => $user->id,
        ]);

        Log::info('RunnerRuns: Current stop updated', [
            'run_id' => $run->id,
            'stop_id' => $request->input('stop_id'),
            'runner_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Current stop updated.',
            'current_stop_id' => $run->current_stop_id,
        ]);
    }

    /**
     * Format a run for runner display.
     */
    protected function formatRunForRunner(RunInstance $run): array
    {
        $openItems = $run->requests()
            ->whereHas('status', function ($q) {
                $q->whereNotIn('name', ['delivered', 'cancelled', 'exception']);
            })
            ->count();

        return [
            'id' => $run->id,
            'display_name' => $run->display_name,
            'route_id' => $run->route_id,
            'route_name' => $run->route?->name,
            'scheduled_date' => $run->scheduled_date?->toDateString(),
            'scheduled_time' => $run->scheduled_time?->format('H:i'),
            'status' => $run->status,
            'stop_count' => $run->route?->stops?->count() ?? 0,
            'total_items' => $run->requests->count(),
            'open_items' => $openItems,
            'current_stop_id' => $run->current_stop_id,
            'vehicle' => $run->assignedVehicle ? [
                'id' => $run->assignedVehicle->id,
                'name' => $run->assignedVehicle->name,
            ] : null,
        ];
    }

    /**
     * Get available vehicles for selection.
     */
    public function getVehicles(Request $request): JsonResponse
    {
        // Get parts runner vehicles from service_locations
        $vehicles = ServiceLocation::where('location_type', 'parts_runner_vehicle')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'description' => $v->address ?? null,
            ]);

        // Generic vehicle types
        $genericTypes = [
            ['value' => 'car', 'label' => 'Car'],
            ['value' => 'truck', 'label' => 'Truck'],
            ['value' => 'van', 'label' => 'Van'],
            ['value' => 'suv', 'label' => 'SUV'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        return response()->json([
            'vehicles' => $vehicles,
            'generic_types' => $genericTypes,
        ]);
    }

    /**
     * Get current vehicle session for the runner.
     */
    public function getCurrentVehicle(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = RunnerVehicleSession::getActiveForUser($user->id);

        if (!$session) {
            return response()->json([
                'has_vehicle' => false,
                'vehicle' => null,
            ]);
        }

        $session->load('vehicleLocation');

        return response()->json([
            'has_vehicle' => true,
            'vehicle' => [
                'session_id' => $session->id,
                'is_generic' => $session->is_generic,
                'vehicle_location_id' => $session->vehicle_location_id,
                'vehicle_name' => $session->vehicle_display_name,
                'generic_vehicle_type' => $session->generic_vehicle_type,
                'generic_vehicle_description' => $session->generic_vehicle_description,
                'generic_license_plate' => $session->generic_license_plate,
                'started_at' => $session->started_at,
            ],
        ]);
    }

    /**
     * Select a vehicle for the runner's session.
     */
    public function selectVehicle(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle_location_id' => 'nullable|exists:service_locations,id',
            'is_generic' => 'required|boolean',
            'generic_vehicle_type' => 'required_if:is_generic,true|nullable|string|in:car,truck,van,suv,other',
            'generic_vehicle_description' => 'nullable|string|max:255',
            'generic_license_plate' => 'nullable|string|max:20',
            'run_id' => 'nullable|exists:run_instances,id',
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            if ($request->input('is_generic')) {
                $session = RunnerVehicleSession::startWithGeneric(
                    $user->id,
                    $request->input('generic_vehicle_type'),
                    $request->input('generic_vehicle_description'),
                    $request->input('generic_license_plate'),
                    $request->input('run_id')
                );
            } else {
                $session = RunnerVehicleSession::startWithVehicle(
                    $user->id,
                    $request->input('vehicle_location_id'),
                    $request->input('run_id')
                );
            }

            // If run_id provided, update run's assigned vehicle
            if ($request->input('run_id') && !$request->input('is_generic')) {
                RunInstance::where('id', $request->input('run_id'))
                    ->update([
                        'assigned_vehicle_location_id' => $request->input('vehicle_location_id'),
                        'updated_by' => $user->id,
                    ]);
            }

            DB::commit();

            $session->load('vehicleLocation');

            Log::info('RunnerRuns: Vehicle selected', [
                'user_id' => $user->id,
                'session_id' => $session->id,
                'is_generic' => $session->is_generic,
                'vehicle_name' => $session->vehicle_display_name,
            ]);

            return response()->json([
                'message' => 'Vehicle selected successfully.',
                'vehicle' => [
                    'session_id' => $session->id,
                    'is_generic' => $session->is_generic,
                    'vehicle_location_id' => $session->vehicle_location_id,
                    'vehicle_name' => $session->vehicle_display_name,
                    'generic_vehicle_type' => $session->generic_vehicle_type,
                    'generic_vehicle_description' => $session->generic_vehicle_description,
                    'generic_license_plate' => $session->generic_license_plate,
                    'started_at' => $session->started_at,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('RunnerRuns: Failed to select vehicle', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to select vehicle.',
            ], 500);
        }
    }

    /**
     * End the current vehicle session.
     */
    public function endVehicleSession(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = RunnerVehicleSession::getActiveForUser($user->id);

        if (!$session) {
            return response()->json([
                'message' => 'No active vehicle session.',
            ], 404);
        }

        $session->end();

        Log::info('RunnerRuns: Vehicle session ended', [
            'user_id' => $user->id,
            'session_id' => $session->id,
        ]);

        return response()->json([
            'message' => 'Vehicle session ended.',
        ]);
    }
}
