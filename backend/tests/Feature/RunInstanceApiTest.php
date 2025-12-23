<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\RunInstance;
use App\Models\ServiceLocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunInstanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $runner;
    protected Route $route;
    protected ServiceLocation $shopLocation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create runner user
        $this->runner = User::factory()->create();
        $this->actingAs($this->runner, 'sanctum');

        // Create shop location
        $this->shopLocation = ServiceLocation::factory()->create([
            'location_type' => 'fixed_shop',
        ]);

        // Create route with stops
        $this->route = Route::factory()->create([
            'start_location_id' => $this->shopLocation->id,
        ]);

        RouteStop::factory()->create([
            'route_id' => $this->route->id,
            'location_id' => $this->shopLocation->id,
            'stop_order' => 1,
        ]);
    }

    public function test_can_list_runs(): void
    {
        RunInstance::factory()->count(3)->create([
            'route_id' => $this->route->id,
            'runner_id' => $this->runner->id,
            'scheduled_date' => Carbon::today(),
        ]);

        $response = $this->getJson('/api/runs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'route_id',
                        'runner_id',
                        'scheduled_date',
                        'scheduled_time',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_run(): void
    {
        $runData = [
            'route_id' => $this->route->id,
            'runner_id' => $this->runner->id,
            'scheduled_date' => Carbon::today()->toDateString(),
            'scheduled_time' => '08:00:00',
        ];

        $response = $this->postJson('/api/runs', $runData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'route_id' => $this->route->id,
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('run_instances', [
            'route_id' => $this->route->id,
            'runner_id' => $this->runner->id,
        ]);
    }

    public function test_can_start_run(): void
    {
        $run = RunInstance::factory()->create([
            'route_id' => $this->route->id,
            'runner_id' => $this->runner->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/start");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'in_progress']);

        $this->assertDatabaseHas('run_instances', [
            'id' => $run->id,
            'status' => 'in_progress',
        ]);

        $this->assertNotNull($run->fresh()->actual_start_at);
    }

    public function test_can_complete_run(): void
    {
        $run = RunInstance::factory()->create([
            'route_id' => $this->route->id,
            'runner_id' => $this->runner->id,
            'status' => 'in_progress',
            'actual_start_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/complete");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'completed']);

        $this->assertDatabaseHas('run_instances', [
            'id' => $run->id,
            'status' => 'completed',
        ]);

        $this->assertNotNull($run->fresh()->actual_end_at);
    }

    public function test_can_arrive_at_stop(): void
    {
        $run = RunInstance::factory()->create([
            'route_id' => $this->route->id,
            'runner_id' => $this->runner->id,
            'status' => 'in_progress',
        ]);

        $stop = $this->route->stops()->first();

        $response = $this->postJson("/api/runs/{$run->id}/stops/{$stop->id}/arrive");

        $response->assertStatus(200);

        $this->assertDatabaseHas('run_stop_actuals', [
            'run_instance_id' => $run->id,
            'route_stop_id' => $stop->id,
        ]);
    }

    public function test_can_depart_from_stop(): void
    {
        $run = RunInstance::factory()->create([
            'route_id' => $this->route->id,
            'runner_id' => $this->runner->id,
            'status' => 'in_progress',
        ]);

        $stop = $this->route->stops()->first();

        // First arrive at stop
        $this->postJson("/api/runs/{$run->id}/stops/{$stop->id}/arrive");

        // Then depart
        $response = $this->postJson("/api/runs/{$run->id}/stops/{$stop->id}/depart");

        $response->assertStatus(200);

        $actual = $run->stopActuals()->where('route_stop_id', $stop->id)->first();
        $this->assertNotNull($actual->departed_at);
    }

    public function test_can_fetch_my_runs(): void
    {
        // Create runs for this runner
        RunInstance::factory()->count(2)->create([
            'route_id' => $this->route->id,
            'runner_id' => $this->runner->id,
            'scheduled_date' => Carbon::today(),
        ]);

        // Create run for different runner
        $otherRunner = User::factory()->create();
        RunInstance::factory()->create([
            'route_id' => $this->route->id,
            'runner_id' => $otherRunner->id,
            'scheduled_date' => Carbon::today(),
        ]);

        $response = $this->getJson('/api/runs/my-runs?date=' . Carbon::today()->toDateString());

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_add_run_note(): void
    {
        $run = RunInstance::factory()->create([
            'route_id' => $this->route->id,
            'runner_id' => $this->runner->id,
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/notes", [
            'note' => 'Traffic delay on route',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('run_notes', [
            'run_instance_id' => $run->id,
            'note' => 'Traffic delay on route',
            'created_by' => $this->runner->id,
        ]);
    }
}
