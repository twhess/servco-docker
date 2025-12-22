<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\RouteSchedule;
use App\Services\RouteGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RouteController extends Controller
{
    protected RouteGraphService $routeGraphService;

    public function __construct(RouteGraphService $routeGraphService)
    {
        $this->routeGraphService = $routeGraphService;
    }

    /**
     * GET /routes - List all routes
     */
    public function index(Request $request)
    {
        $query = Route::with(['startLocation', 'stops', 'schedules']);

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $routes = $query->orderBy('name')->get();

        return response()->json([
            'data' => $routes,
        ]);
    }

    /**
     * GET /routes/{id} - Single route details
     */
    public function show(int $id)
    {
        $route = Route::with([
            'startLocation',
            'stops.location',
            'stops.vendorClusterLocations.vendorLocation',
            'schedules',
            'runInstances' => function ($query) {
                $query->where('scheduled_date', '>=', today())->orderBy('scheduled_date');
            }
        ])->findOrFail($id);

        return response()->json([
            'data' => $route,
        ]);
    }

    /**
     * POST /routes - Create route
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:routes,code',
            'description' => 'nullable|string',
            'start_location_id' => 'required|exists:service_locations,id',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $route = Route::create($validated);

        Log::info("Route created: #{$route->id} - {$route->name}");

        return response()->json([
            'message' => 'Route created successfully',
            'data' => $route->load('startLocation'),
        ], 201);
    }

    /**
     * PUT /routes/{id} - Update route
     */
    public function update(Request $request, int $id)
    {
        $route = Route::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:routes,code,' . $id,
            'description' => 'nullable|string',
            'start_location_id' => 'sometimes|exists:service_locations,id',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by'] = auth()->id();

        $route->update($validated);

        // Rebuild cache if route was modified
        $this->routeGraphService->rebuildCache();

        Log::info("Route updated: #{$route->id} - {$route->name}");

        return response()->json([
            'message' => 'Route updated successfully',
            'data' => $route->load('startLocation'),
        ]);
    }

    /**
     * DELETE /routes/{id} - Soft delete (deactivate)
     */
    public function destroy(int $id)
    {
        $route = Route::findOrFail($id);

        $route->update([
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        // Rebuild cache after deactivation
        $this->routeGraphService->rebuildCache();

        Log::info("Route deactivated: #{$route->id} - {$route->name}");

        return response()->json([
            'message' => 'Route deactivated successfully',
        ]);
    }

    /**
     * POST /routes/{id}/activate - Reactivate route
     */
    public function activate(int $id)
    {
        $route = Route::findOrFail($id);

        $route->update([
            'is_active' => true,
            'updated_by' => auth()->id(),
        ]);

        // Rebuild cache after activation
        $this->routeGraphService->rebuildCache();

        Log::info("Route activated: #{$route->id} - {$route->name}");

        return response()->json([
            'message' => 'Route activated successfully',
            'data' => $route,
        ]);
    }

    /**
     * POST /routes/{id}/stops - Add stop to route
     */
    public function addStop(Request $request, int $id)
    {
        $route = Route::findOrFail($id);

        $validated = $request->validate([
            'stop_type' => 'required|in:SHOP,VENDOR_CLUSTER,CUSTOMER,AD_HOC',
            'location_id' => 'nullable|exists:service_locations,id',
            'stop_order' => 'required|integer|min:1',
            'estimated_duration_minutes' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'vendor_locations' => 'array', // For VENDOR_CLUSTER type
            'vendor_locations.*.vendor_location_id' => 'exists:service_locations,id',
            'vendor_locations.*.location_order' => 'integer|min:0',
            'vendor_locations.*.is_optional' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $validated['route_id'] = $route->id;
            $validated['created_by'] = auth()->id();
            $validated['updated_by'] = auth()->id();

            $stop = RouteStop::create($validated);

            // Add vendor cluster locations if provided
            if ($validated['stop_type'] === 'VENDOR_CLUSTER' && !empty($validated['vendor_locations'])) {
                foreach ($validated['vendor_locations'] as $vendorLocation) {
                    $stop->vendorClusterLocations()->create([
                        'vendor_location_id' => $vendorLocation['vendor_location_id'],
                        'location_order' => $vendorLocation['location_order'] ?? 0,
                        'is_optional' => $vendorLocation['is_optional'] ?? false,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);
                }
            }

            DB::commit();

            // Rebuild cache
            $this->routeGraphService->rebuildCache();

            Log::info("Stop added to route #{$route->id}: Stop #{$stop->id}");

            return response()->json([
                'message' => 'Stop added successfully',
                'data' => $stop->load(['location', 'vendorClusterLocations.vendorLocation']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to add stop to route #{$route->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * PUT /routes/{id}/stops/{stopId} - Update stop
     */
    public function updateStop(Request $request, int $id, int $stopId)
    {
        $route = Route::findOrFail($id);
        $stop = RouteStop::where('route_id', $route->id)
            ->where('id', $stopId)
            ->firstOrFail();

        $validated = $request->validate([
            'stop_type' => 'sometimes|in:SHOP,VENDOR_CLUSTER,CUSTOMER,AD_HOC',
            'location_id' => 'nullable|exists:service_locations,id',
            'stop_order' => 'sometimes|integer|min:1',
            'estimated_duration_minutes' => 'sometimes|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $validated['updated_by'] = auth()->id();

        $stop->update($validated);

        // Rebuild cache
        $this->routeGraphService->rebuildCache();

        Log::info("Stop updated: #{$stop->id} on route #{$route->id}");

        return response()->json([
            'message' => 'Stop updated successfully',
            'data' => $stop->load(['location', 'vendorClusterLocations.vendorLocation']),
        ]);
    }

    /**
     * DELETE /routes/{id}/stops/{stopId} - Remove stop
     */
    public function removeStop(int $id, int $stopId)
    {
        $route = Route::findOrFail($id);
        $stop = RouteStop::where('route_id', $route->id)
            ->where('id', $stopId)
            ->firstOrFail();

        $stop->delete();

        // Rebuild cache
        $this->routeGraphService->rebuildCache();

        Log::info("Stop removed: #{$stop->id} from route #{$route->id}");

        return response()->json([
            'message' => 'Stop removed successfully',
        ]);
    }

    /**
     * POST /routes/{id}/stops/reorder - Reorder stops
     */
    public function reorderStops(Request $request, int $id)
    {
        $route = Route::findOrFail($id);

        $validated = $request->validate([
            'stops' => 'required|array',
            'stops.*.id' => 'required|exists:route_stops,id',
            'stops.*.order' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['stops'] as $stopData) {
                RouteStop::where('id', $stopData['id'])
                    ->where('route_id', $route->id)
                    ->update([
                        'stop_order' => $stopData['order'],
                        'updated_by' => auth()->id(),
                    ]);
            }

            DB::commit();

            // Rebuild cache
            $this->routeGraphService->rebuildCache();

            Log::info("Stops reordered on route #{$route->id}");

            return response()->json([
                'message' => 'Stops reordered successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to reorder stops on route #{$route->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * POST /routes/{id}/schedules - Add scheduled time
     */
    public function addSchedule(Request $request, int $id)
    {
        $route = Route::findOrFail($id);

        $validated = $request->validate([
            'scheduled_time' => 'required|date_format:H:i',
            'is_active' => 'boolean',
        ]);

        $validated['route_id'] = $route->id;
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $schedule = RouteSchedule::create($validated);

        Log::info("Schedule added to route #{$route->id}: {$validated['scheduled_time']}");

        return response()->json([
            'message' => 'Schedule added successfully',
            'data' => $schedule,
        ], 201);
    }

    /**
     * DELETE /routes/{id}/schedules/{scheduleId} - Remove time
     */
    public function removeSchedule(int $id, int $scheduleId)
    {
        $route = Route::findOrFail($id);
        $schedule = RouteSchedule::where('route_id', $route->id)
            ->where('id', $scheduleId)
            ->firstOrFail();

        $schedule->delete();

        Log::info("Schedule removed: #{$schedule->id} from route #{$route->id}");

        return response()->json([
            'message' => 'Schedule removed successfully',
        ]);
    }

    /**
     * POST /routes/rebuild-cache - Trigger cache rebuild
     */
    public function rebuildCache()
    {
        $this->routeGraphService->rebuildCache();

        return response()->json([
            'message' => 'Route graph cache rebuilt successfully',
        ]);
    }

    /**
     * GET /routes/path - Find path between locations
     */
    public function findPath(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|exists:service_locations,id',
            'to' => 'required|exists:service_locations,id',
        ]);

        $path = $this->routeGraphService->findPath($validated['from'], $validated['to']);

        if (!$path) {
            return response()->json([
                'message' => 'No path found between these locations',
                'requires_manual_routing' => true,
            ], 404);
        }

        return response()->json([
            'data' => $path,
        ]);
    }
}
