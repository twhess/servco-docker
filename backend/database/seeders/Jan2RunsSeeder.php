<?php

namespace Database\Seeders;

use App\Models\Route;
use App\Models\RouteSchedule;
use App\Models\RunInstance;
use App\Models\User;
use Illuminate\Database\Seeder;

class Jan2RunsSeeder extends Seeder
{
    /**
     * Seed multiple runs for January 2, 2026 to test merge functionality.
     */
    public function run(): void
    {
        $this->command->info('Seeding runs for January 2, 2026...');

        $targetDate = '2026-01-02';

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

        // Get routes
        $routes = Route::with(['schedules'])->where('is_active', true)->get();

        if ($routes->isEmpty()) {
            $this->command->warn('No routes found. Please run RouteSeeder first.');
            return;
        }

        // Clear existing runs for target date to avoid duplicates
        RunInstance::whereDate('scheduled_date', $targetDate)->delete();

        $this->command->info('Creating runs for: ' . $targetDate);

        // Get specific routes
        $northLoop = $routes->firstWhere('code', 'NORTH-01');
        $southLoop = $routes->firstWhere('code', 'SOUTH-01');
        $expressRoute = $routes->firstWhere('code', 'EXPRESS-01');
        $customerRoute = $routes->firstWhere('code', 'CUSTOMER-01');

        $runCount = 0;

        // ========================================
        // NORTH LOOP - 3 pending runs (good for merge testing)
        // ========================================
        if ($northLoop) {
            // Morning run - 8:00 AM
            $schedule = $this->getScheduleForTime($northLoop, '08:00:00');
            RunInstance::create([
                'route_id' => $northLoop->id,
                'route_schedule_id' => $schedule?->id,
                'scheduled_date' => $targetDate,
                'scheduled_time' => '08:00:00',
                'assigned_runner_user_id' => $runner1->id,
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $runCount++;
            $this->command->info("  Created: {$northLoop->name} @ 8:00 AM - Mike Runner");

            // Mid-morning run - 10:30 AM
            RunInstance::create([
                'route_id' => $northLoop->id,
                'route_schedule_id' => null,
                'scheduled_date' => $targetDate,
                'scheduled_time' => '10:30:00',
                'assigned_runner_user_id' => $runner2->id,
                'status' => 'pending',
                'is_on_demand' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $runCount++;
            $this->command->info("  Created: {$northLoop->name} @ 10:30 AM - Sarah Driver (on-demand)");

            // Afternoon run - 2:00 PM
            $schedule = $this->getScheduleForTime($northLoop, '14:00:00');
            RunInstance::create([
                'route_id' => $northLoop->id,
                'route_schedule_id' => $schedule?->id,
                'scheduled_date' => $targetDate,
                'scheduled_time' => '14:00:00',
                'assigned_runner_user_id' => $runner3->id,
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $runCount++;
            $this->command->info("  Created: {$northLoop->name} @ 2:00 PM - Tom Wheeler");
        }

        // ========================================
        // SOUTH LOOP - 2 pending runs (good for merge testing)
        // ========================================
        if ($southLoop) {
            // Morning run - 9:00 AM
            $schedule = $this->getScheduleForTime($southLoop, '09:00:00');
            RunInstance::create([
                'route_id' => $southLoop->id,
                'route_schedule_id' => $schedule?->id,
                'scheduled_date' => $targetDate,
                'scheduled_time' => '09:00:00',
                'assigned_runner_user_id' => $runner2->id,
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $runCount++;
            $this->command->info("  Created: {$southLoop->name} @ 9:00 AM - Sarah Driver");

            // Afternoon run - 3:00 PM
            $schedule = $this->getScheduleForTime($southLoop, '15:00:00');
            RunInstance::create([
                'route_id' => $southLoop->id,
                'route_schedule_id' => $schedule?->id,
                'scheduled_date' => $targetDate,
                'scheduled_time' => '15:00:00',
                'assigned_runner_user_id' => null, // Unassigned
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $runCount++;
            $this->command->info("  Created: {$southLoop->name} @ 3:00 PM - UNASSIGNED");
        }

        // ========================================
        // EXPRESS ROUTE - 2 pending runs (good for merge testing)
        // ========================================
        if ($expressRoute) {
            // Morning run - 10:00 AM
            $schedule = $this->getScheduleForTime($expressRoute, '10:00:00');
            RunInstance::create([
                'route_id' => $expressRoute->id,
                'route_schedule_id' => $schedule?->id,
                'scheduled_date' => $targetDate,
                'scheduled_time' => '10:00:00',
                'assigned_runner_user_id' => $runner1->id,
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $runCount++;
            $this->command->info("  Created: {$expressRoute->name} @ 10:00 AM - Mike Runner");

            // Afternoon run - 1:00 PM
            $schedule = $this->getScheduleForTime($expressRoute, '13:00:00');
            RunInstance::create([
                'route_id' => $expressRoute->id,
                'route_schedule_id' => $schedule?->id,
                'scheduled_date' => $targetDate,
                'scheduled_time' => '13:00:00',
                'assigned_runner_user_id' => $runner1->id,
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $runCount++;
            $this->command->info("  Created: {$expressRoute->name} @ 1:00 PM - Mike Runner");
        }

        // ========================================
        // CUSTOMER ROUTE - 1 pending run (no merge available)
        // ========================================
        if ($customerRoute) {
            // Noon run - 12:00 PM
            $schedule = $this->getScheduleForTime($customerRoute, '12:00:00');
            RunInstance::create([
                'route_id' => $customerRoute->id,
                'route_schedule_id' => $schedule?->id,
                'scheduled_date' => $targetDate,
                'scheduled_time' => '12:00:00',
                'assigned_runner_user_id' => $runner3->id,
                'status' => 'pending',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $runCount++;
            $this->command->info("  Created: {$customerRoute->name} @ 12:00 PM - Tom Wheeler (single run, no merge option)");
        }

        $this->command->newLine();
        $this->command->info('=== Jan 2, 2026 Runs Seeder Complete ===');
        $this->command->info("Created {$runCount} runs for {$targetDate}");
        $this->command->newLine();
        $this->command->info('Merge testing scenarios:');
        $this->command->info('  - North Loop: 3 pending runs (can merge any combination)');
        $this->command->info('  - South Loop: 2 pending runs (can merge)');
        $this->command->info('  - Express Route: 2 pending runs (can merge)');
        $this->command->info('  - Customer Route: 1 run only (merge button should NOT appear)');
        $this->command->newLine();
        $this->command->info('Test users:');
        $this->command->info('  dispatcher@example.com / password');
        $this->command->info('  runner1@example.com / password (Mike Runner)');
        $this->command->info('  runner2@example.com / password (Sarah Driver)');
        $this->command->info('  runner3@example.com / password (Tom Wheeler)');
    }

    /**
     * Get a schedule for a route at a specific time.
     */
    private function getScheduleForTime(Route $route, string $time): ?RouteSchedule
    {
        return RouteSchedule::where('route_id', $route->id)
            ->where('scheduled_time', $time)
            ->first();
    }
}
