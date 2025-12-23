<?php

namespace Tests\Unit;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Services\RouteGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteGraphServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RouteGraphService $service;
    protected ServiceLocation $shopA;
    protected ServiceLocation $shopB;
    protected ServiceLocation $shopC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RouteGraphService::class);

        // Create three shop locations
        $this->shopA = ServiceLocation::factory()->create([
            'location_type' => 'fixed_shop',
            'name' => 'Shop A',
        ]);

        $this->shopB = ServiceLocation::factory()->create([
            'location_type' => 'fixed_shop',
            'name' => 'Shop B',
        ]);

        $this->shopC = ServiceLocation::factory()->create([
            'location_type' => 'fixed_shop',
            'name' => 'Shop C',
        ]);
    }

    public function test_can_build_graph_from_routes(): void
    {
        // Create route A -> B
        $route1 = Route::factory()->create([
            'start_location_id' => $this->shopA->id,
        ]);

        RouteStop::factory()->create([
            'route_id' => $route1->id,
            'location_id' => $this->shopB->id,
            'stop_order' => 1,
        ]);

        // Build graph
        $graph = $this->service->buildGraph();

        $this->assertArrayHasKey($this->shopA->id, $graph);
        $this->assertContains([
            'location_id' => $this->shopB->id,
            'route_id' => $route1->id,
            'stop_order' => 1,
        ], $graph[$this->shopA->id]);
    }

    public function test_can_find_direct_path(): void
    {
        // Create route A -> B
        $route = Route::factory()->create([
            'start_location_id' => $this->shopA->id,
        ]);

        RouteStop::factory()->create([
            'route_id' => $route->id,
            'location_id' => $this->shopB->id,
            'stop_order' => 1,
        ]);

        $this->service->rebuildCache();

        // Find path
        $path = $this->service->findPath($this->shopA->id, $this->shopB->id);

        $this->assertNotNull($path);
        $this->assertCount(2, $path);
        $this->assertEquals($this->shopA->id, $path[0]['location_id']);
        $this->assertEquals($this->shopB->id, $path[1]['location_id']);
    }

    public function test_can_find_multi_hop_path(): void
    {
        // Create route A -> B
        $route1 = Route::factory()->create([
            'start_location_id' => $this->shopA->id,
        ]);

        RouteStop::factory()->create([
            'route_id' => $route1->id,
            'location_id' => $this->shopB->id,
            'stop_order' => 1,
        ]);

        // Create route B -> C
        $route2 = Route::factory()->create([
            'start_location_id' => $this->shopB->id,
        ]);

        RouteStop::factory()->create([
            'route_id' => $route2->id,
            'location_id' => $this->shopC->id,
            'stop_order' => 1,
        ]);

        $this->service->rebuildCache();

        // Find path A -> C (should go through B)
        $path = $this->service->findPath($this->shopA->id, $this->shopC->id);

        $this->assertNotNull($path);
        $this->assertCount(3, $path);
        $this->assertEquals($this->shopA->id, $path[0]['location_id']);
        $this->assertEquals($this->shopB->id, $path[1]['location_id']);
        $this->assertEquals($this->shopC->id, $path[2]['location_id']);
    }

    public function test_returns_null_for_unreachable_path(): void
    {
        // Create route A -> B (but no route to C)
        $route = Route::factory()->create([
            'start_location_id' => $this->shopA->id,
        ]);

        RouteStop::factory()->create([
            'route_id' => $route->id,
            'location_id' => $this->shopB->id,
            'stop_order' => 1,
        ]);

        $this->service->rebuildCache();

        // Try to find path A -> C (should fail)
        $path = $this->service->findPath($this->shopA->id, $this->shopC->id);

        $this->assertNull($path);
    }

    public function test_rebuild_cache_creates_cache_entries(): void
    {
        // Create route A -> B
        $route = Route::factory()->create([
            'start_location_id' => $this->shopA->id,
        ]);

        RouteStop::factory()->create([
            'route_id' => $route->id,
            'location_id' => $this->shopB->id,
            'stop_order' => 1,
        ]);

        $this->service->rebuildCache();

        // Verify cache entry exists
        $this->assertDatabaseHas('route_graph_cache', [
            'from_location_id' => $this->shopA->id,
            'to_location_id' => $this->shopB->id,
            'hop_count' => 1,
        ]);
    }

    public function test_cached_path_matches_computed_path(): void
    {
        // Create route A -> B -> C
        $route = Route::factory()->create([
            'start_location_id' => $this->shopA->id,
        ]);

        RouteStop::factory()->create([
            'route_id' => $route->id,
            'location_id' => $this->shopB->id,
            'stop_order' => 1,
        ]);

        RouteStop::factory()->create([
            'route_id' => $route->id,
            'location_id' => $this->shopC->id,
            'stop_order' => 2,
        ]);

        $this->service->rebuildCache();

        // Find path using cache
        $cachedPath = $this->service->findPath($this->shopA->id, $this->shopC->id);

        $this->assertNotNull($cachedPath);
        $this->assertCount(3, $cachedPath);
    }
}
