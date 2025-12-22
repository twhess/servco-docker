<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RunInstance;
use App\Models\PartsRequest;
use App\Models\RouteStop;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunSchedulerService
{
    protected RouteGraphService $routeGraphService;

    public function __construct(RouteGraphService $routeGraphService)
    {
        $this->routeGraphService = $routeGraphService;
    }

    /**
     * Get or create a run instance for a route at a specific date/time
     */
    public function getOrCreateRunInstance(int $routeId, Carbon $date, string $time): RunInstance
    {
        $route = Route::findOrFail($routeId);

        return RunInstance::firstOrCreate(
            [
                'route_id' => $routeId,
                'scheduled_date' => $date,
                'scheduled_time' => $time,
            ],
            [
                'status' => 'pending',
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
            ]
        );
    }

    /**
     * Find next available run for a route after a given time
     *
     * @param int $routeId
     * @param Carbon $after
     * @param Carbon|null $forDate Specific date for forward scheduling
     * @return RunInstance|null
     */
    public function findNextAvailableRun(int $routeId, Carbon $after, ?Carbon $forDate = null): ?RunInstance
    {
        return $this->routeGraphService->findNextAvailableRun($routeId, $after, $forDate);
    }

    /**
     * Assign a request to a specific run and stop
     */
    public function assignRequestToRun(PartsRequest $request, int $runInstanceId, int $pickupStopId, int $dropoffStopId): void
    {
        $run = RunInstance::findOrFail($runInstanceId);

        // Validate stops belong to the run's route
        $pickupStop = RouteStop::where('id', $pickupStopId)
            ->where('route_id', $run->route_id)
            ->firstOrFail();

        $dropoffStop = RouteStop::where('id', $dropoffStopId)
            ->where('route_id', $run->route_id)
            ->firstOrFail();

        // If request has scheduled_for_date, ensure run date matches
        if ($request->scheduled_for_date && !$run->scheduled_date->eq($request->scheduled_for_date)) {
            throw new \Exception("Run date ({$run->scheduled_date->toDateString()}) does not match request scheduled date ({$request->scheduled_for_date->toDateString()})");
        }

        // Update request
        $request->update([
            'run_instance_id' => $runInstanceId,
            'pickup_stop_id' => $pickupStopId,
            'dropoff_stop_id' => $dropoffStopId,
            'updated_by' => auth()->id() ?? 1,
        ]);

        // Create assignment event
        $request->events()->create([
            'event_type' => 'assigned_to_run',
            'notes' => "Assigned to run #{$run->id} on {$run->scheduled_date->toDateString()} at {$run->scheduled_time}",
            'event_at' => now(),
            'user_id' => auth()->id(),
        ]);

        // Update run status if first request assigned
        if ($run->status === 'pending' && $run->requests()->count() === 1) {
            $run->update(['status' => 'pending']);
        }

        Log::info("Request #{$request->id} assigned to run #{$run->id}");
    }

    /**
     * Auto-assign a request to appropriate run based on origin/destination
     *
     * @return bool True if successfully assigned, false if needs manual routing
     */
    public function autoAssignRequest(PartsRequest $request): bool
    {
        // Skip if already has override
        if ($request->hasOverride()) {
            Log::info("Request #{$request->id} has admin override, skipping auto-assignment");
            return true;
        }

        $originId = $request->origin_location_id;
        $destinationId = $request->receiving_location_id;

        if (!$originId || !$destinationId) {
            Log::warning("Request #{$request->id} missing origin or destination, cannot auto-assign");
            return false;
        }

        // Find path between locations
        $path = $this->routeGraphService->findPath($originId, $destinationId);

        if (!$path) {
            Log::warning("No path found from location #{$originId} to #{$destinationId} for request #{$request->id}");
            return false;
        }

        // Direct route (single hop)
        if ($path['hops'] === 1) {
            return $this->assignDirectRoute($request, $path);
        }

        // Multi-leg routing required
        return $this->assignMultiLegRoute($request, $path);
    }

    /**
     * Assign request to a direct route (single hop)
     */
    private function assignDirectRoute(PartsRequest $request, array $path): bool
    {
        $routeId = $path['routes'][0];
        $targetDate = $request->scheduled_for_date ?? now();

        // Find next available run
        $run = $this->findNextAvailableRun($routeId, now(), $request->scheduled_for_date);
        if (!$run) {
            Log::warning("No available run found for route #{$routeId}");
            return false;
        }

        // Find pickup and dropoff stops
        $pickupStop = $run->route->stops()
            ->where('location_id', $request->origin_location_id)
            ->orWhereHas('vendorClusterLocations', function ($q) use ($request) {
                $q->where('vendor_location_id', $request->origin_location_id);
            })
            ->first();

        $dropoffStop = $run->route->stops()
            ->where('location_id', $request->receiving_location_id)
            ->orWhereHas('vendorClusterLocations', function ($q) use ($request) {
                $q->where('vendor_location_id', $request->receiving_location_id);
            })
            ->first();

        if (!$pickupStop || !$dropoffStop) {
            Log::warning("Could not find pickup or dropoff stops for request #{$request->id}");
            return false;
        }

        $this->assignRequestToRun($request, $run->id, $pickupStop->id, $dropoffStop->id);
        return true;
    }

    /**
     * Assign request using multi-leg routing (create segments)
     */
    private function assignMultiLegRoute(PartsRequest $request, array $path): bool
    {
        DB::beginTransaction();
        try {
            // Create child segments
            $segmentIds = $this->routeGraphService->createSegments($request, $path);

            // Mark parent as multi-leg
            $request->update([
                'updated_by' => auth()->id() ?? 1,
            ]);

            // Create event
            $request->events()->create([
                'event_type' => 'multi_leg_created',
                'notes' => "Multi-leg routing created with {$path['hops']} segments",
                'event_at' => now(),
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            Log::info("Multi-leg routing created for request #{$request->id} with " . count($segmentIds) . " segments");
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create multi-leg routing for request #{$request->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process all scheduled requests for a specific date
     * Daily job: Makes future-scheduled requests visible and assigns them
     */
    public function processScheduledRequests(Carbon $date): void
    {
        Log::info("Processing scheduled requests for {$date->toDateString()}");

        $requests = PartsRequest::scheduledFor($date)
            ->whereNull('run_instance_id') // Not yet assigned
            ->get();

        $processed = 0;
        $assigned = 0;
        $needsManual = 0;

        foreach ($requests as $request) {
            $processed++;

            if ($this->autoAssignRequest($request)) {
                $assigned++;
            } else {
                $needsManual++;
            }
        }

        Log::info("Scheduled requests processing complete: {$processed} processed, {$assigned} assigned, {$needsManual} need manual routing");

        // Notify dispatchers if any need manual intervention
        if ($needsManual > 0) {
            // TODO: Send notification via NotificationService
        }
    }

    /**
     * Unassign a request from its current run
     */
    public function unassignRequest(PartsRequest $request, string $reason): void
    {
        $oldRunId = $request->run_instance_id;

        $request->update([
            'run_instance_id' => null,
            'pickup_stop_id' => null,
            'dropoff_stop_id' => null,
            'updated_by' => auth()->id() ?? 1,
        ]);

        // Create unassignment event
        $request->events()->create([
            'event_type' => 'unassigned_from_run',
            'notes' => "Unassigned from run #{$oldRunId}. Reason: {$reason}",
            'event_at' => now(),
            'user_id' => auth()->id(),
        ]);

        Log::info("Request #{$request->id} unassigned from run #{$oldRunId}");
    }

    /**
     * Reassign request to next available run on same route
     * Used when runner marks pickup as "Not Ready"
     */
    public function reassignToNextRun(PartsRequest $request): bool
    {
        $currentRun = $request->runInstance;
        if (!$currentRun) {
            return false;
        }

        // Find next run on same route
        $nextRun = $this->findNextAvailableRun(
            $currentRun->route_id,
            now(),
            $request->scheduled_for_date
        );

        if (!$nextRun || $nextRun->id === $currentRun->id) {
            Log::warning("No next run available for request #{$request->id} on route #{$currentRun->route_id}");
            return false;
        }

        // Reassign to next run with same stops
        $this->assignRequestToRun(
            $request,
            $nextRun->id,
            $request->pickup_stop_id,
            $request->dropoff_stop_id
        );

        return true;
    }
}
