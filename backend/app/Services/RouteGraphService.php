<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RouteGraphCache;
use App\Models\RunInstance;
use App\Models\PartsRequest;
use App\Models\ServiceLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RouteGraphService
{
    /**
     * Build adjacency list graph from all active routes
     *
     * @return array Format: [location_id => [['location_id' => int, 'route_id' => int], ...]]
     */
    public function buildGraph(): array
    {
        return Cache::remember('route_graph', 3600, function () {
            $graph = [];

            // Get all active routes with their stops
            $routes = Route::active()
                ->with(['stops' => function ($query) {
                    $query->orderBy('stop_order');
                }])
                ->get();

            foreach ($routes as $route) {
                $stops = $route->stops;

                // Build edges between consecutive stops
                for ($i = 0; $i < count($stops) - 1; $i++) {
                    $currentStop = $stops[$i];
                    $nextStop = $stops[$i + 1];

                    // Handle vendor clusters (no location_id)
                    $currentLocations = $this->getStopLocations($currentStop);
                    $nextLocations = $this->getStopLocations($nextStop);

                    // Create edges between all combinations
                    foreach ($currentLocations as $fromLocationId) {
                        foreach ($nextLocations as $toLocationId) {
                            if (!isset($graph[$fromLocationId])) {
                                $graph[$fromLocationId] = [];
                            }

                            $graph[$fromLocationId][] = [
                                'location_id' => $toLocationId,
                                'route_id' => $route->id,
                            ];
                        }
                    }
                }
            }

            return $graph;
        });
    }

    /**
     * Get all location IDs for a stop (handles vendor clusters)
     * Note: Vendor clusters no longer map to service_locations directly.
     * They reference the vendors table. For routing purposes, vendor
     * clusters are treated as a single stop without specific location IDs.
     */
    private function getStopLocations($stop): array
    {
        if ($stop->isVendorCluster()) {
            // Vendor clusters don't have direct location IDs anymore
            // Return empty array - routing to vendors is handled separately
            return [];
        }

        return $stop->location_id ? [$stop->location_id] : [];
    }

    /**
     * Find shortest path between two locations using BFS
     *
     * @return array|null ['path' => [location_ids], 'routes' => [route_ids], 'hops' => int]
     */
    public function findPath(int $fromLocationId, int $toLocationId): ?array
    {
        // Check cache first
        $cached = $this->getCachedPath($fromLocationId, $toLocationId);
        if ($cached && !$cached->isStale()) {
            // Filter out null route_ids from cached data
            $routes = array_values(array_filter(
                array_column($cached->path_json, 'route_id'),
                fn($r) => $r !== null
            ));
            return [
                'path' => $cached->path_json,
                'routes' => $routes,
                'hops' => $cached->hop_count,
            ];
        }

        // Build graph
        $graph = $this->buildGraph();

        // BFS to find shortest path
        $queue = [[$fromLocationId]];
        $visited = [$fromLocationId => true];
        $parent = [];
        $routeUsed = [];

        while (!empty($queue)) {
            $path = array_shift($queue);
            $current = end($path);

            // Found destination
            if ($current === $toLocationId) {
                return $this->buildPathResult($path, $parent, $routeUsed, $fromLocationId, $toLocationId);
            }

            // Explore neighbors
            if (isset($graph[$current])) {
                foreach ($graph[$current] as $edge) {
                    $neighbor = $edge['location_id'];

                    if (!isset($visited[$neighbor])) {
                        $visited[$neighbor] = true;
                        $parent[$neighbor] = $current;
                        $routeUsed[$neighbor] = $edge['route_id'];

                        $newPath = $path;
                        $newPath[] = $neighbor;
                        $queue[] = $newPath;
                    }
                }
            }
        }

        // No path found
        $this->cacheNoPath($fromLocationId, $toLocationId);
        return null;
    }

    /**
     * Build path result from BFS traversal
     */
    private function buildPathResult(array $path, array $parent, array $routeUsed, int $from, int $to): array
    {
        $pathData = [];
        $routes = [];

        foreach ($path as $index => $locationId) {
            $location = ServiceLocation::find($locationId);
            $routeId = $routeUsed[$locationId] ?? null;

            $pathData[] = [
                'location_id' => $locationId,
                'location_name' => $location->name ?? "Location #{$locationId}",
                'route_id' => $routeId,
            ];

            // Only add non-null routes and avoid duplicates
            if ($routeId !== null && !in_array($routeId, $routes, true)) {
                $routes[] = $routeId;
            }
        }

        $result = [
            'path' => $pathData,
            'routes' => $routes,
            'hops' => count($routes),
        ];

        // Cache the result
        $this->cachePath($from, $to, $pathData, count($routes));

        return $result;
    }

    /**
     * Find next available run instance for a route
     *
     * Returns run info including Saturday detection for user prompts.
     * If is_saturday is true, frontend should prompt user to choose Saturday or next business day.
     *
     * @param int $routeId
     * @param Carbon $after Find runs after this time
     * @param Carbon|null $forDate Specific date (for forward scheduling)
     * @param int|null $pickupLocationId Location where pickup needs to happen (for passed-stop check)
     * @param bool $skipClosedDates Whether to skip holidays/vacations
     * @return array|null ['run' => RunInstance, 'is_saturday' => bool, 'date' => Carbon, 'schedule_id' => int]
     */
    public function findNextAvailableRun(
        int $routeId,
        Carbon $after,
        ?Carbon $forDate = null,
        ?int $pickupLocationId = null,
        bool $skipClosedDates = true
    ): ?array {
        $route = Route::find($routeId);
        if (!$route || !$route->is_active) {
            return null;
        }

        $today = $forDate ?? $after->copy()->startOfDay();

        // Skip closed dates check for today
        if ($skipClosedDates && \App\Models\ClosedDate::isDateClosed($today)) {
            // Today is closed, skip to schedule-based lookup for next open day
            return $this->findNextAvailableRunFromSchedule($route, $after, $forDate, $pickupLocationId, $skipClosedDates);
        }

        // STEP 1: Get all active schedules for today and check/create runs for each
        $schedules = $route->schedules()
            ->where('is_active', true)
            ->get()
            ->filter(fn($s) => $s->runsOnDate($today))
            ->sortBy(fn($s) => $s->scheduled_time?->format('H:i') ?? '00:00');

        foreach ($schedules as $schedule) {
            $scheduleTime = $schedule->scheduled_time?->format('H:i');
            if (!$scheduleTime) {
                continue;
            }

            // Check if run instance already exists for this schedule today
            $run = RunInstance::where('route_id', $routeId)
                ->whereDate('scheduled_date', $today)
                ->where('scheduled_time', $scheduleTime)
                ->first();

            if ($run) {
                // Run exists - check if it's still available
                if (!$run->isAvailableForAssignment()) {
                    continue; // Completed/cancelled, try next schedule
                }

                if ($pickupLocationId && $run->hasPassedLocation($pickupLocationId)) {
                    continue; // Already passed pickup point, try next schedule
                }

                // Found an available existing run
                Log::debug("Found existing available run #{$run->id} for route #{$routeId} at {$scheduleTime}");

                return [
                    'run' => $run,
                    'is_saturday' => $today->isSaturday(),
                    'date' => $today,
                    'time' => $scheduleTime,
                    'schedule_id' => $schedule->id,
                ];
            } else {
                // No run exists for this schedule - create one if it makes sense
                // A schedule is valid for new runs if:
                // - It's a future time today, OR
                // - We're explicitly looking for today's runs (forDate is set)
                $scheduleDateTime = $today->copy()->setTimeFromTimeString($scheduleTime);
                $isFutureTime = $scheduleDateTime->gt($after);

                // Always create the run for today's schedules - even if time has passed
                // The run being "pending" means items can still be assigned to it
                // (e.g., morning run at 8am, it's now 9am, but run hasn't departed yet)
                Log::debug("Creating new run for route #{$routeId} at {$scheduleTime} on {$today->toDateString()}");

                $run = RunInstance::create([
                    'route_id' => $routeId,
                    'scheduled_date' => $today->toDateString(),
                    'scheduled_time' => $scheduleTime,
                    'route_schedule_id' => $schedule->id,
                    'status' => 'pending',
                    'created_by' => auth()->id() ?? 1,
                    'updated_by' => auth()->id() ?? 1,
                ]);

                return [
                    'run' => $run,
                    'is_saturday' => $today->isSaturday(),
                    'date' => $today,
                    'time' => $scheduleTime,
                    'schedule_id' => $schedule->id,
                ];
            }
        }

        // No valid schedules for today, look for future runs
        return $this->findNextAvailableRunFromSchedule($route, $after, $forDate, $pickupLocationId, $skipClosedDates);
    }

    /**
     * Find next available run using schedule-based lookup (for future dates)
     */
    private function findNextAvailableRunFromSchedule(
        Route $route,
        Carbon $after,
        ?Carbon $forDate,
        ?int $pickupLocationId,
        bool $skipClosedDates
    ): ?array {
        $scheduleResult = $route->getNextScheduledDateTime($after, $forDate, $skipClosedDates);
        if (!$scheduleResult) {
            return null;
        }

        $targetDate = $scheduleResult['date'];
        $scheduledTime = $scheduleResult['time'];

        // Check if run instance already exists for this date/time
        $run = RunInstance::where('route_id', $route->id)
            ->whereDate('scheduled_date', $targetDate)
            ->where('scheduled_time', $scheduledTime)
            ->first();

        // If run exists, check if it's still available
        if ($run) {
            // Check if run has passed the pickup location
            if ($pickupLocationId && $run->hasPassedLocation($pickupLocationId)) {
                // This run has already passed our pickup point, find the next one
                return $this->findNextAvailableRun(
                    $route->id,
                    $targetDate->copy()->setTimeFromTimeString($scheduledTime)->addMinute(),
                    null,
                    $pickupLocationId,
                    $skipClosedDates
                );
            }

            // Check if run is still accepting assignments
            if (!$run->isAvailableForAssignment()) {
                // Run is completed/cancelled, find the next one
                return $this->findNextAvailableRun(
                    $route->id,
                    $targetDate->copy()->setTimeFromTimeString($scheduledTime)->addMinute(),
                    null,
                    $pickupLocationId,
                    $skipClosedDates
                );
            }
        } else {
            // Create new run instance
            $run = RunInstance::create([
                'route_id' => $route->id,
                'scheduled_date' => $targetDate->toDateString(),
                'scheduled_time' => $scheduledTime,
                'route_schedule_id' => $scheduleResult['schedule_id'] ?? null,
                'status' => 'pending',
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
            ]);
        }

        return [
            'run' => $run,
            'is_saturday' => $scheduleResult['is_saturday'],
            'date' => $targetDate,
            'time' => $scheduledTime,
            'schedule_id' => $scheduleResult['schedule_id'] ?? null,
        ];
    }

    /**
     * Find next available run, skipping Saturday if user declines
     * This is called after user responds to Saturday prompt
     *
     * @param int $routeId
     * @param Carbon $afterSaturday The Saturday date to skip
     * @param int|null $pickupLocationId Location where pickup needs to happen
     * @return array|null
     */
    public function findNextBusinessDayRun(
        int $routeId,
        Carbon $afterSaturday,
        ?int $pickupLocationId = null
    ): ?array {
        // Start searching from the day after the Saturday
        $nextDay = $afterSaturday->copy()->addDay()->startOfDay();

        return $this->findNextAvailableRun(
            $routeId,
            $nextDay,
            null,
            $pickupLocationId,
            true // Always skip closed dates
        );
    }

    /**
     * Create child segment requests for multi-leg routing
     *
     * @param PartsRequest $request Parent request
     * @param array $path Path data from findPath()
     * @return array Array of created segment IDs
     */
    public function createSegments(PartsRequest $request, array $path): array
    {
        $segmentIds = [];
        $pathData = $path['path'];

        // Create segments for each leg
        for ($i = 0; $i < count($pathData) - 1; $i++) {
            $fromLocation = $pathData[$i];
            $toLocation = $pathData[$i + 1];
            $routeId = $fromLocation['route_id'];

            // Create segment request
            $segment = PartsRequest::create([
                'parent_request_id' => $request->id,
                'segment_order' => $i + 1,
                'is_segment' => true,
                'request_type_id' => $request->request_type_id,
                'origin_location_id' => $fromLocation['location_id'],
                'receiving_location_id' => $toLocation['location_id'],
                'urgency_id' => $request->urgency_id,
                'status_id' => $request->status_id,
                'details' => "Segment " . ($i + 1) . " of {$request->reference_number}",
                'reference_number' => $request->reference_number . "-S" . ($i + 1),
                'requested_by_user_id' => $request->requested_by_user_id,
                'requested_at' => $request->requested_at,
                'item_id' => $request->item_id, // Link to same item
                'scheduled_for_date' => $request->scheduled_for_date, // Preserve scheduling
                'created_by' => $request->created_by,
                'updated_by' => $request->updated_by,
            ]);

            // Find appropriate run and stops for this segment
            $runResult = $this->findNextAvailableRun(
                $routeId,
                now(),
                $request->scheduled_for_date,
                $fromLocation['location_id'] // Check against pickup location
            );

            if ($runResult && $runResult['run']) {
                $run = $runResult['run'];
                $pickupStop = $run->route->stops()
                    ->where('location_id', $fromLocation['location_id'])
                    ->first();

                $dropoffStop = $run->route->stops()
                    ->where('location_id', $toLocation['location_id'])
                    ->first();

                if ($pickupStop && $dropoffStop) {
                    $segment->update([
                        'run_instance_id' => $run->id,
                        'pickup_stop_id' => $pickupStop->id,
                        'dropoff_stop_id' => $dropoffStop->id,
                    ]);
                }
            }

            $segmentIds[] = $segment->id;
        }

        return $segmentIds;
    }

    /**
     * Calculate estimated arrival time at a location for a run instance
     */
    public function calculateETA(int $runInstanceId, int $locationId): ?Carbon
    {
        $run = RunInstance::find($runInstanceId);
        if (!$run) {
            return null;
        }

        // Find the stop for this location
        $stop = $run->route->stops()
            ->where('location_id', $locationId)
            ->first();

        if (!$stop) {
            return null;
        }

        // Use actual start time if available, otherwise scheduled time
        $startTime = $run->actual_start_at ?? Carbon::parse($run->scheduled_date->format('Y-m-d') . ' ' . $run->scheduled_time);

        return $stop->getEstimatedArrivalTime($startTime);
    }

    /**
     * Get cached path between two locations
     */
    public function getCachedPath(int $from, int $to): ?RouteGraphCache
    {
        return RouteGraphCache::where('from_location_id', $from)
            ->where('to_location_id', $to)
            ->first();
    }

    /**
     * Cache a successful path
     */
    private function cachePath(int $from, int $to, array $pathJson, int $hopCount): void
    {
        RouteGraphCache::updateOrCreate(
            [
                'from_location_id' => $from,
                'to_location_id' => $to,
            ],
            [
                'path_json' => $pathJson,
                'hop_count' => $hopCount,
                'requires_manual_routing' => false,
                'cached_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Cache when no path exists (requires manual routing)
     */
    private function cacheNoPath(int $from, int $to): void
    {
        RouteGraphCache::updateOrCreate(
            [
                'from_location_id' => $from,
                'to_location_id' => $to,
            ],
            [
                'path_json' => [],
                'hop_count' => 0,
                'requires_manual_routing' => true,
                'cached_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Rebuild entire cache (run when routes are modified)
     */
    public function rebuildCache(): void
    {
        Log::info('Starting route graph cache rebuild');
        $startTime = microtime(true);

        // Clear old cache
        Cache::forget('route_graph');
        RouteGraphCache::truncate();

        // Get all locations
        $locations = ServiceLocation::all();
        $pathsComputed = 0;

        // Compute paths between all location pairs
        foreach ($locations as $from) {
            foreach ($locations as $to) {
                if ($from->id === $to->id) {
                    continue;
                }

                $this->findPath($from->id, $to->id);
                $pathsComputed++;
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        Log::info("Route graph cache rebuild complete. Computed {$pathsComputed} paths in {$duration}s");
    }
}
