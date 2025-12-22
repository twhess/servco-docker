<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding routes...');

        // Get authenticated user for created_by/updated_by
        $adminUser = DB::table('users')->where('email', 'like', '%admin%')->first();
        $userId = $adminUser ? $adminUser->id : 1;

        // Get location IDs
        $locations = DB::table('service_locations')->get()->keyBy('name');

        if ($locations->isEmpty()) {
            $this->command->warn('No service locations found. Please seed locations first.');
            return;
        }

        $now = Carbon::now();

        // Route 1: North Loop (Main shop to northern vendors and back)
        $northLoopRoute = [
            'name' => 'North Loop',
            'code' => 'NORTH-01',
            'description' => 'Daily route covering northern vendor locations',
            'start_location_id' => $locations->firstWhere('location_type', 'fixed_shop')->id ?? 1,
            'is_active' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $northLoopId = DB::table('routes')->insertGetId($northLoopRoute);

        // Add stops for North Loop
        $this->addRouteStops($northLoopId, [
            ['type' => 'VENDOR_CLUSTER', 'location_id' => null, 'order' => 1, 'duration' => 30, 'notes' => 'Northern vendors'],
            ['type' => 'SHOP', 'location_id' => $northLoopRoute['start_location_id'], 'order' => 2, 'duration' => 15, 'notes' => 'Return to shop'],
        ], $userId);

        // Add schedules for North Loop (Morning and afternoon runs)
        $this->addSchedules($northLoopId, ['08:00:00', '14:00:00']);

        // Route 2: South Loop
        $southLoopRoute = [
            'name' => 'South Loop',
            'code' => 'SOUTH-01',
            'description' => 'Daily route covering southern vendor locations',
            'start_location_id' => $locations->firstWhere('location_type', 'fixed_shop')->id ?? 1,
            'is_active' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $southLoopId = DB::table('routes')->insertGetId($southLoopRoute);

        $this->addRouteStops($southLoopId, [
            ['type' => 'VENDOR_CLUSTER', 'location_id' => null, 'order' => 1, 'duration' => 30, 'notes' => 'Southern vendors'],
            ['type' => 'SHOP', 'location_id' => $southLoopRoute['start_location_id'], 'order' => 2, 'duration' => 15, 'notes' => 'Return to shop'],
        ], $userId);

        $this->addSchedules($southLoopId, ['09:00:00', '15:00:00']);

        // Route 3: Express Route (Quick turnaround for urgent requests)
        $expressRoute = [
            'name' => 'Express Route',
            'code' => 'EXPRESS-01',
            'description' => 'Multiple daily runs for urgent parts requests',
            'start_location_id' => $locations->firstWhere('location_type', 'fixed_shop')->id ?? 1,
            'is_active' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $expressId = DB::table('routes')->insertGetId($expressRoute);

        $this->addRouteStops($expressId, [
            ['type' => 'VENDOR_CLUSTER', 'location_id' => null, 'order' => 1, 'duration' => 20, 'notes' => 'Nearby vendors only'],
            ['type' => 'SHOP', 'location_id' => $expressRoute['start_location_id'], 'order' => 2, 'duration' => 10, 'notes' => 'Quick return'],
        ], $userId);

        // Express runs multiple times per day
        $this->addSchedules($expressId, ['07:00:00', '10:00:00', '13:00:00', '16:00:00']);

        // Route 4: Shop-to-Shop Transfer Route
        $shops = $locations->where('location_type', 'fixed_shop')->values();
        if ($shops->count() >= 2) {
            $transferRoute = [
                'name' => 'Shop Transfer Route',
                'code' => 'TRANSFER-01',
                'description' => 'Inter-shop parts transfer route',
                'start_location_id' => $shops[0]->id,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $transferId = DB::table('routes')->insertGetId($transferRoute);

            $this->addRouteStops($transferId, [
                ['type' => 'SHOP', 'location_id' => $shops[1]->id ?? $shops[0]->id, 'order' => 1, 'duration' => 20, 'notes' => 'Transfer stop'],
                ['type' => 'SHOP', 'location_id' => $shops[0]->id, 'order' => 2, 'duration' => 15, 'notes' => 'Return to origin'],
            ], $userId);

            $this->addSchedules($transferId, ['11:00:00']);
        }

        // Route 5: Customer Delivery Route
        $customerRoute = [
            'name' => 'Customer Delivery Route',
            'code' => 'CUSTOMER-01',
            'description' => 'Route for customer site deliveries',
            'start_location_id' => $locations->firstWhere('location_type', 'fixed_shop')->id ?? 1,
            'is_active' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $customerId = DB::table('routes')->insertGetId($customerRoute);

        $this->addRouteStops($customerId, [
            ['type' => 'CUSTOMER', 'location_id' => null, 'order' => 1, 'duration' => 30, 'notes' => 'Customer sites'],
            ['type' => 'SHOP', 'location_id' => $customerRoute['start_location_id'], 'order' => 2, 'duration' => 15, 'notes' => 'Return to shop'],
        ], $userId);

        $this->addSchedules($customerId, ['12:00:00']);

        $this->command->info('Routes seeded successfully!');
    }

    /**
     * Add route stops
     */
    private function addRouteStops(int $routeId, array $stops, int $userId): void
    {
        $now = Carbon::now();

        foreach ($stops as $stop) {
            DB::table('route_stops')->insert([
                'route_id' => $routeId,
                'stop_type' => $stop['type'],
                'location_id' => $stop['location_id'],
                'stop_order' => $stop['order'],
                'estimated_duration_minutes' => $stop['duration'],
                'notes' => $stop['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Add route schedules
     */
    private function addSchedules(int $routeId, array $times): void
    {
        $now = Carbon::now();
        $userId = DB::table('users')->where('email', 'like', '%admin%')->first()->id ?? 1;

        foreach ($times as $time) {
            DB::table('route_schedules')->insert([
                'route_id' => $routeId,
                'scheduled_time' => $time,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
