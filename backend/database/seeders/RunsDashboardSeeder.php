<?php

namespace Database\Seeders;

use App\Models\PartsRequest;
use App\Models\PartsRequestStatus;
use App\Models\PartsRequestType;
use App\Models\Route;
use App\Models\RouteSchedule;
use App\Models\RouteStop;
use App\Models\RunInstance;
use App\Models\RunStopActual;
use App\Models\ServiceLocation;
use App\Models\UrgencyLevel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RunsDashboardSeeder extends Seeder
{
    /**
     * Seed runs for today with various statuses to test the dashboard.
     */
    public function run(): void
    {
        $this->command->info('Seeding runs dashboard test data...');

        // Get or create users
        $dispatcher = User::firstOrCreate(
            ['email' => 'dispatcher@example.com'],
            [
                'username' => 'dispatcher',
                'password' => bcrypt('password'),
                'first_name' => 'John',
                'last_name' => 'Dispatcher',
                'role' => 'dispatcher',
                'active' => true,
            ]
        );

        $runner1 = User::firstOrCreate(
            ['email' => 'runner1@example.com'],
            [
                'username' => 'runner1',
                'password' => bcrypt('password'),
                'first_name' => 'Mike',
                'last_name' => 'Runner',
                'role' => 'runner_driver',
                'active' => true,
            ]
        );

        $runner2 = User::firstOrCreate(
            ['email' => 'runner2@example.com'],
            [
                'username' => 'runner2',
                'password' => bcrypt('password'),
                'first_name' => 'Sarah',
                'last_name' => 'Driver',
                'role' => 'runner_driver',
                'active' => true,
            ]
        );

        $runner3 = User::firstOrCreate(
            ['email' => 'runner3@example.com'],
            [
                'username' => 'runner3',
                'password' => bcrypt('password'),
                'first_name' => 'Tom',
                'last_name' => 'Wheeler',
                'role' => 'runner_driver',
                'active' => true,
            ]
        );

        $userId = $dispatcher->id;
        $today = Carbon::today()->format('Y-m-d');

        // Get routes (should exist from RouteSeeder)
        $routes = Route::with('stops')->get();

        if ($routes->isEmpty()) {
            $this->command->warn('No routes found. Please run RouteSeeder first.');
            return;
        }

        // Get lookup data
        $pickupType = PartsRequestType::where('name', 'pickup')->first();
        $transferType = PartsRequestType::where('name', 'transfer')->first();
        $deliveryType = PartsRequestType::where('name', 'delivery')->first();

        $newStatus = PartsRequestStatus::where('name', 'new')->first();
        $assignedStatus = PartsRequestStatus::where('name', 'assigned')->first();
        $enRoutePickupStatus = PartsRequestStatus::where('name', 'en_route_pickup')->first();
        $pickedUpStatus = PartsRequestStatus::where('name', 'picked_up')->first();
        $deliveredStatus = PartsRequestStatus::where('name', 'delivered')->first();

        $normalUrgency = UrgencyLevel::where('name', 'normal')->first();
        $todayUrgency = UrgencyLevel::where('name', 'today')->first();
        $asapUrgency = UrgencyLevel::where('name', 'asap')->first();

        // Get locations
        $locations = ServiceLocation::where('location_type', 'fixed_shop')->limit(2)->get();
        $mainShop = $locations->first();

        // Clear existing runs for today to avoid duplicates
        RunInstance::whereDate('scheduled_date', $today)->delete();

        $this->command->info('Creating runs for today: ' . $today);

        // ========================================
        // RUN 1: Completed run (North Loop morning)
        // ========================================
        $northLoop = $routes->firstWhere('code', 'NORTH-01');
        $northLoopMorning = RouteSchedule::where('route_id', $northLoop?->id)
            ->where('scheduled_time', '08:00:00')
            ->first();
        $northLoopAfternoon = RouteSchedule::where('route_id', $northLoop?->id)
            ->where('scheduled_time', '14:00:00')
            ->first();

        if ($northLoop) {
            $run1 = RunInstance::create([
                'route_id' => $northLoop->id,
                'route_schedule_id' => $northLoopMorning?->id,
                'scheduled_date' => $today,
                'scheduled_time' => '08:00:00',
                'assigned_runner_user_id' => $runner1->id,
                'status' => 'completed',
                'actual_start_at' => Carbon::today()->setTime(8, 5),
                'actual_end_at' => Carbon::today()->setTime(10, 30),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Create stop actuals for completed run
            foreach ($northLoop->stops as $stop) {
                $baseTime = Carbon::today()->setTime(8, 5);
                $arriveOffset = ($stop->stop_order - 1) * 45; // 45 min between stops
                $departOffset = $arriveOffset + 30; // 30 min at each stop

                RunStopActual::create([
                    'run_instance_id' => $run1->id,
                    'route_stop_id' => $stop->id,
                    'arrived_at' => $baseTime->copy()->addMinutes($arriveOffset),
                    'departed_at' => $baseTime->copy()->addMinutes($departOffset),
                    'tasks_completed' => 3,
                    'tasks_total' => 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->command->info("  Created completed run: {$northLoop->name} (8:00 AM)");
        }

        // ========================================
        // RUN 2: In Progress run (South Loop morning) - halfway through
        // ========================================
        $southLoop = $routes->firstWhere('code', 'SOUTH-01');
        $southLoopMorning = RouteSchedule::where('route_id', $southLoop?->id)
            ->where('scheduled_time', '09:00:00')
            ->first();
        $southLoopAfternoon = RouteSchedule::where('route_id', $southLoop?->id)
            ->where('scheduled_time', '15:00:00')
            ->first();

        if ($southLoop) {
            $run2 = RunInstance::create([
                'route_id' => $southLoop->id,
                'route_schedule_id' => $southLoopMorning?->id,
                'scheduled_date' => $today,
                'scheduled_time' => '09:00:00',
                'assigned_runner_user_id' => $runner2->id,
                'status' => 'in_progress',
                'actual_start_at' => Carbon::today()->setTime(9, 10),
                'current_stop_id' => $southLoop->stops->first()?->id,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Create stop actuals - first stop arrived but not departed
            $stops = $southLoop->stops->sortBy('stop_order');
            $firstStop = $stops->first();
            if ($firstStop) {
                RunStopActual::create([
                    'run_instance_id' => $run2->id,
                    'route_stop_id' => $firstStop->id,
                    'arrived_at' => Carbon::today()->setTime(9, 25),
                    'departed_at' => null, // Still at this stop
                    'tasks_completed' => 1,
                    'tasks_total' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Second stop not yet visited
                $secondStop = $stops->skip(1)->first();
                if ($secondStop) {
                    RunStopActual::create([
                        'run_instance_id' => $run2->id,
                        'route_stop_id' => $secondStop->id,
                        'arrived_at' => null,
                        'departed_at' => null,
                        'tasks_completed' => 0,
                        'tasks_total' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Add some parts requests to this run
            if ($pickupType && $mainShop) {
                PartsRequest::create([
                    'reference_number' => PartsRequest::generateReferenceNumber(),
                    'request_type_id' => $pickupType->id,
                    'vendor_name' => 'AutoZone',
                    'origin_address' => '555 Main St, Lima, OH 45801',
                    'origin_lat' => 40.7450,
                    'origin_lng' => -84.1100,
                    'receiving_location_id' => $mainShop->id,
                    'requested_at' => now()->subHours(3),
                    'requested_by_user_id' => $dispatcher->id,
                    'pickup_run' => true,
                    'urgency_id' => $todayUrgency->id,
                    'status_id' => $enRoutePickupStatus->id,
                    'details' => 'Oil filter and air filter for service truck',
                    'run_instance_id' => $run2->id,
                    'pickup_stop_id' => $firstStop?->id,
                    'assigned_runner_user_id' => $runner2->id,
                    'assigned_at' => now()->subHours(2),
                ]);
            }

            $this->command->info("  Created in-progress run: {$southLoop->name} (9:00 AM) - Sarah Driver");
        }

        // ========================================
        // RUN 3: In Progress run (Express Route) - just started
        // ========================================
        $expressRoute = $routes->firstWhere('code', 'EXPRESS-01');
        $expressMorning = RouteSchedule::where('route_id', $expressRoute?->id)
            ->where('scheduled_time', '10:00:00')
            ->first();
        $expressAfternoon = RouteSchedule::where('route_id', $expressRoute?->id)
            ->where('scheduled_time', '13:00:00')
            ->first();

        if ($expressRoute) {
            $run3 = RunInstance::create([
                'route_id' => $expressRoute->id,
                'route_schedule_id' => $expressMorning?->id,
                'scheduled_date' => $today,
                'scheduled_time' => '10:00:00',
                'assigned_runner_user_id' => $runner1->id,
                'status' => 'in_progress',
                'actual_start_at' => Carbon::today()->setTime(10, 2),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Create stop actuals - all not yet visited
            foreach ($expressRoute->stops as $stop) {
                RunStopActual::create([
                    'run_instance_id' => $run3->id,
                    'route_stop_id' => $stop->id,
                    'arrived_at' => null,
                    'departed_at' => null,
                    'tasks_completed' => 0,
                    'tasks_total' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->command->info("  Created in-progress run: {$expressRoute->name} (10:00 AM) - Mike Runner - just started");
        }

        // ========================================
        // RUN 4: Pending run (assigned but not started)
        // ========================================
        if ($northLoop) {
            $run4 = RunInstance::create([
                'route_id' => $northLoop->id,
                'route_schedule_id' => $northLoopAfternoon?->id,
                'scheduled_date' => $today,
                'scheduled_time' => '14:00:00',
                'assigned_runner_user_id' => $runner3->id,
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->command->info("  Created pending run: {$northLoop->name} - Afternoon (2:00 PM) - Tom Wheeler assigned");
        }

        // ========================================
        // RUN 5: Pending run (unassigned)
        // ========================================
        if ($southLoop) {
            $run5 = RunInstance::create([
                'route_id' => $southLoop->id,
                'route_schedule_id' => $southLoopAfternoon?->id,
                'scheduled_date' => $today,
                'scheduled_time' => '15:00:00',
                'assigned_runner_user_id' => null, // Unassigned
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->command->info("  Created pending run: {$southLoop->name} - Afternoon (3:00 PM) - UNASSIGNED");
        }

        // ========================================
        // RUN 6: Pending Express run (afternoon)
        // ========================================
        if ($expressRoute) {
            $run6 = RunInstance::create([
                'route_id' => $expressRoute->id,
                'route_schedule_id' => $expressAfternoon?->id,
                'scheduled_date' => $today,
                'scheduled_time' => '13:00:00',
                'assigned_runner_user_id' => $runner2->id,
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->command->info("  Created pending run: {$expressRoute->name} - Afternoon (1:00 PM) - Sarah Driver");
        }

        // ========================================
        // RUN 7: Canceled run
        // ========================================
        $customerRoute = $routes->firstWhere('code', 'CUSTOMER-01');
        $customerNoon = RouteSchedule::where('route_id', $customerRoute?->id)
            ->where('scheduled_time', '12:00:00')
            ->first();

        if ($customerRoute) {
            $run7 = RunInstance::create([
                'route_id' => $customerRoute->id,
                'route_schedule_id' => $customerNoon?->id,
                'scheduled_date' => $today,
                'scheduled_time' => '12:00:00',
                'assigned_runner_user_id' => $runner1->id,
                'status' => 'canceled',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->command->info("  Created canceled run: {$customerRoute->name} - Afternoon (12:00 PM)");
        }

        // ========================================
        // Additional Parts Requests for testing
        // ========================================
        if ($pickupType && $mainShop && $asapUrgency && $newStatus) {
            PartsRequest::create([
                'reference_number' => PartsRequest::generateReferenceNumber(),
                'request_type_id' => $pickupType->id,
                'vendor_name' => 'NAPA Auto Parts',
                'origin_address' => '123 Commerce Dr, Lima, OH 45801',
                'origin_lat' => 40.7400,
                'origin_lng' => -84.1050,
                'receiving_location_id' => $mainShop->id,
                'requested_at' => now()->subMinutes(45),
                'requested_by_user_id' => $dispatcher->id,
                'pickup_run' => true,
                'urgency_id' => $asapUrgency->id,
                'status_id' => $newStatus->id,
                'details' => 'URGENT: Brake rotors for customer vehicle - truck down!',
                'special_instructions' => 'Call when 5 minutes away',
            ]);

            $this->command->info('  Created ASAP parts request (unassigned)');
        }

        if ($transferType && $locations->count() >= 2 && $normalUrgency && $assignedStatus) {
            PartsRequest::create([
                'reference_number' => PartsRequest::generateReferenceNumber(),
                'request_type_id' => $transferType->id,
                'origin_location_id' => $locations[0]->id,
                'receiving_location_id' => $locations[1]->id,
                'requested_at' => now()->subHours(1),
                'requested_by_user_id' => $dispatcher->id,
                'pickup_run' => false,
                'urgency_id' => $normalUrgency->id,
                'status_id' => $assignedStatus->id,
                'details' => 'Transfer of specialty tools to West Shop',
                'assigned_runner_user_id' => $runner3->id,
                'assigned_at' => now()->subMinutes(30),
            ]);

            $this->command->info('  Created transfer request (assigned to Tom)');
        }

        $this->command->newLine();
        $this->command->info('=== Runs Dashboard Seeder Complete ===');
        $this->command->info('Created runs for today with the following statuses:');
        $this->command->info('  - 1 Completed run');
        $this->command->info('  - 2-3 In Progress runs');
        $this->command->info('  - 3 Pending runs (1 unassigned)');
        $this->command->info('  - 1 Canceled run');
        $this->command->newLine();
        $this->command->info('Test users:');
        $this->command->info('  dispatcher@example.com / password');
        $this->command->info('  runner1@example.com / password (Mike Runner)');
        $this->command->info('  runner2@example.com / password (Sarah Driver)');
        $this->command->info('  runner3@example.com / password (Tom Wheeler)');
    }
}
