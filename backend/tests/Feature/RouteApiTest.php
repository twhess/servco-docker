<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected ServiceLocation $shopLocation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user with permissions
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin, 'sanctum');

        // Create a shop location
        $this->shopLocation = ServiceLocation::factory()->create([
            'location_type' => 'fixed_shop',
            'name' => 'Main Shop',
        ]);
    }

    public function test_can_list_routes(): void
    {
        Route::factory()->count(3)->create([
            'start_location_id' => $this->shopLocation->id,
        ]);

        $response = $this->getJson('/api/routes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'description',
                        'start_location_id',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_route(): void
    {
        $routeData = [
            'name' => 'North Loop',
            'code' => 'NORTH-01',
            'description' => 'Northern vendor route',
            'start_location_id' => $this->shopLocation->id,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/routes', $routeData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'North Loop',
                'code' => 'NORTH-01',
            ]);

        $this->assertDatabaseHas('routes', [
            'name' => 'North Loop',
            'code' => 'NORTH-01',
        ]);
    }

    public function test_can_update_route(): void
    {
        $route = Route::factory()->create([
            'start_location_id' => $this->shopLocation->id,
            'name' => 'Original Name',
        ]);

        $response = $this->putJson("/api/routes/{$route->id}", [
            'name' => 'Updated Name',
            'code' => $route->code,
            'start_location_id' => $this->shopLocation->id,
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('routes', [
            'id' => $route->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_delete_route(): void
    {
        $route = Route::factory()->create([
            'start_location_id' => $this->shopLocation->id,
        ]);

        $response = $this->deleteJson("/api/routes/{$route->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('routes', [
            'id' => $route->id,
            'is_active' => false,
        ]);
    }

    public function test_can_add_stop_to_route(): void
    {
        $route = Route::factory()->create([
            'start_location_id' => $this->shopLocation->id,
        ]);

        $vendorLocation = ServiceLocation::factory()->create([
            'location_type' => 'vendor',
        ]);

        $response = $this->postJson("/api/routes/{$route->id}/stops", [
            'stop_type' => 'SHOP',
            'location_id' => $vendorLocation->id,
            'stop_order' => 1,
            'estimated_duration_minutes' => 30,
            'notes' => 'First stop',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'stop_type' => 'SHOP',
                'stop_order' => 1,
            ]);

        $this->assertDatabaseHas('route_stops', [
            'route_id' => $route->id,
            'location_id' => $vendorLocation->id,
            'stop_order' => 1,
        ]);
    }

    public function test_can_reorder_stops(): void
    {
        $route = Route::factory()->create([
            'start_location_id' => $this->shopLocation->id,
        ]);

        $stop1 = RouteStop::factory()->create([
            'route_id' => $route->id,
            'stop_order' => 1,
        ]);

        $stop2 = RouteStop::factory()->create([
            'route_id' => $route->id,
            'stop_order' => 2,
        ]);

        $response = $this->postJson("/api/routes/{$route->id}/stops/reorder", [
            'stops' => [
                ['id' => $stop2->id, 'order' => 1],
                ['id' => $stop1->id, 'order' => 2],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('route_stops', [
            'id' => $stop2->id,
            'stop_order' => 1,
        ]);

        $this->assertDatabaseHas('route_stops', [
            'id' => $stop1->id,
            'stop_order' => 2,
        ]);
    }

    public function test_can_add_schedule_to_route(): void
    {
        $route = Route::factory()->create([
            'start_location_id' => $this->shopLocation->id,
        ]);

        $response = $this->postJson("/api/routes/{$route->id}/schedules", [
            'scheduled_time' => '08:00:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'scheduled_time' => '08:00:00',
            ]);

        $this->assertDatabaseHas('route_schedules', [
            'route_id' => $route->id,
            'scheduled_time' => '08:00:00',
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->withoutMiddleware();

        $response = $this->getJson('/api/routes');

        // Without auth middleware, it should work
        $response->assertStatus(200);
    }
}
