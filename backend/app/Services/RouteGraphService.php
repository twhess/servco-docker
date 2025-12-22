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
     */
    private function getStopLocations($stop): array
    {
        if ($stop->isVendorCluster()) {
            // Get all vendor locations in this cluster
            return $stop->vendorClusterLocations()
                ->pluck('vendor_location_id')
                ->toArray();
        }

        return [$stop->location_id];
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
            return [
                'path' => $cached->path_json,
                'routes' => array_column($cached->path_json, 'route_id'),
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

            $pathData[] = [
                'location_id' => $locationId,
                'location_name' => $location->name ?? "Location #{$locationId}",
                'route_id' => $routeUsed[$locationId] ?? null,
            ];

            if (isset($routeUsed[$locationId]) && !in_array($routeUsed[$locationId], $routes)) {
                $routes[] = $routeUsed[$locationId];
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
     * @param int $routeId
     * @param Carbon $after Find runs after this time
     * @param Carbon|null $forDate Specific date (for forward scheduling)
     * @return RunInstance|null
     */
    public function findNextAvailableRun(int $routeId, Carbon $after, ?Carbon $forDate = null): ?RunInstance
    {
        $route = Route::find($routeId);
        if (!$route || !$route->is_active) {
            return null;
        }

        $targetDate = $forDate ?? $after->copy()->startOfDay();

        // Get next scheduled time for this route
        $nextTime = $route->getNextScheduledTime($after, $forDate);
        if (!$nextTime) {
            return null;
        }

        // Check if run instance already exists
        $run = RunInstance::where('route_id', $routeId)
            ->whereDate('scheduled_date', $targetDate)
            ->where('scheduled_time', $nextTime->format('H:i'))
            ->first();

        // Create if doesn't exist
        if (!$run) {
            $run = $route->createRunInstance($targetDate, $nextTime);
        }

        return $run;
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
            $nextRun = $this->findNextAvailableRun($routeId, now(), $request->scheduled_for_date);
            if ($nextRun) {
                $pickupStop = $nextRun->route->stops()
                    ->where('location_id', $fromLocation['location_id'])
                    ->first();

                $dropoffStop = $nextRun->route->stops()
                    ->where('location_id', $toLocation['location_id'])
                    ->first();

                if ($pickupStop && $dropoffStop) {
                    $segment->update([
                        'run_instance_id' => $nextRun->id,
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
            ->orWhereHas('vendorClusterLocations', function ($q) use ($locationId) {
                $q->where('vendor_location_id', $locationId);
            })
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
