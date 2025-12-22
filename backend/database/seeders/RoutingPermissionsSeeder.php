<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoutingPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Route Management Permissions
            [
                'name' => 'routes.view',
                'display_name' => 'View Routes',
                'description' => 'View route definitions and schedules',
                'module' => 'routes',
            ],
            [
                'name' => 'routes.create',
                'display_name' => 'Create Routes',
                'description' => 'Create new route definitions',
                'module' => 'routes',
            ],
            [
                'name' => 'routes.update',
                'display_name' => 'Update Routes',
                'description' => 'Update existing route definitions',
                'module' => 'routes',
            ],
            [
                'name' => 'routes.delete',
                'display_name' => 'Delete Routes',
                'description' => 'Delete or deactivate routes',
                'module' => 'routes',
            ],
            [
                'name' => 'routes.manage_stops',
                'display_name' => 'Manage Route Stops',
                'description' => 'Add, edit, reorder, and remove route stops',
                'module' => 'routes',
            ],
            [
                'name' => 'routes.manage_schedules',
                'display_name' => 'Manage Route Schedules',
                'description' => 'Add and remove scheduled run times',
                'module' => 'routes',
            ],
            [
                'name' => 'routes.rebuild_cache',
                'display_name' => 'Rebuild Route Cache',
                'description' => 'Rebuild the route graph pathfinding cache',
                'module' => 'routes',
            ],

            // Run Instance Permissions
            [
                'name' => 'runs.view',
                'display_name' => 'View Runs',
                'description' => 'View run instances',
                'module' => 'runs',
            ],
            [
                'name' => 'runs.view_all',
                'display_name' => 'View All Runs',
                'description' => 'View all run instances across all runners',
                'module' => 'runs',
            ],
            [
                'name' => 'runs.create',
                'display_name' => 'Create Runs',
                'description' => 'Manually create run instances',
                'module' => 'runs',
            ],
            [
                'name' => 'runs.cancel',
                'display_name' => 'Cancel Runs',
                'description' => 'Cancel run instances',
                'module' => 'runs',
            ],
            [
                'name' => 'runs.reassign',
                'display_name' => 'Reassign Runs',
                'description' => 'Reassign runs to different runners',
                'module' => 'runs',
            ],
            [
                'name' => 'runs.add_notes',
                'display_name' => 'Add Run Notes',
                'description' => 'Add notes to any run instance',
                'module' => 'runs',
            ],
            [
                'name' => 'runs.view_history',
                'display_name' => 'View Run History',
                'description' => 'View historical run data and analytics',
                'module' => 'runs',
            ],

            // Enhanced Parts Request Permissions
            [
                'name' => 'parts_requests.assign_to_run',
                'display_name' => 'Assign Requests to Runs',
                'description' => 'Assign parts requests to specific run instances',
                'module' => 'parts_requests',
            ],
            [
                'name' => 'parts_requests.override_run',
                'display_name' => 'Override Run Assignment',
                'description' => 'Override automatic run assignment',
                'module' => 'parts_requests',
            ],
            [
                'name' => 'parts_requests.schedule',
                'display_name' => 'Schedule Requests',
                'description' => 'Schedule requests for future dates',
                'module' => 'parts_requests',
            ],
            [
                'name' => 'parts_requests.view_feed',
                'display_name' => 'View Request Feed',
                'description' => 'View dispatcher request feed dashboard',
                'module' => 'parts_requests',
            ],
            [
                'name' => 'parts_requests.archive',
                'display_name' => 'Archive Requests',
                'description' => 'Archive completed or cancelled requests',
                'module' => 'parts_requests',
            ],
            [
                'name' => 'parts_requests.bulk_schedule',
                'display_name' => 'Bulk Schedule Requests',
                'description' => 'Schedule multiple requests at once',
                'module' => 'parts_requests',
            ],
        ];

        $now = now();

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission['name'],
                'display_name' => $permission['display_name'],
                'description' => $permission['description'],
                'module' => $permission['module'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command->info('Routing permissions seeded successfully!');

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    /**
     * Assign routing permissions to default roles
     */
    private function assignPermissionsToRoles(): void
    {
        // Get role IDs
        $adminRole = DB::table('roles')->where('name', 'super_admin')->first();
        $opsAdminRole = DB::table('roles')->where('name', 'ops_admin')->first();
        $dispatcherRole = DB::table('roles')->where('name', 'dispatcher')->first();
        $runnerRole = DB::table('roles')->where('name', 'runner_driver')->first();
        $shopManagerRole = DB::table('roles')->where('name', 'shop_manager')->first();

        if (!$adminRole || !$dispatcherRole || !$runnerRole) {
            $this->command->warn('Some roles not found. Skipping permission assignment.');
            return;
        }

        // Super Admin gets all routing permissions
        $allRoutingPermissions = DB::table('permissions')
            ->whereIn('module', ['routes', 'runs'])
            ->orWhere('name', 'like', 'parts_requests.%_to_run')
            ->orWhere('name', 'like', 'parts_requests.override%')
            ->orWhere('name', 'like', 'parts_requests.schedule%')
            ->orWhere('name', 'like', 'parts_requests.view_feed')
            ->orWhere('name', 'like', 'parts_requests.archive')
            ->orWhere('name', 'like', 'parts_requests.bulk_schedule')
            ->pluck('id');

        foreach ($allRoutingPermissions as $permissionId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $adminRole->id,
                'permission_id' => $permissionId,
            ]);
        }

        // Ops Admin gets all routing permissions
        if ($opsAdminRole) {
            foreach ($allRoutingPermissions as $permissionId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $opsAdminRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        // Dispatcher gets most routing permissions
        $dispatcherPermissions = DB::table('permissions')
            ->where(function ($query) {
                $query->whereIn('module', ['routes', 'runs'])
                    ->orWhereIn('name', [
                        'parts_requests.assign_to_run',
                        'parts_requests.override_run',
                        'parts_requests.schedule',
                        'parts_requests.view_feed',
                        'parts_requests.archive',
                        'parts_requests.bulk_schedule',
                    ]);
            })
            ->whereNotIn('name', ['routes.delete', 'routes.rebuild_cache'])
            ->pluck('id');

        foreach ($dispatcherPermissions as $permissionId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $dispatcherRole->id,
                'permission_id' => $permissionId,
            ]);
        }

        // Runner (runner_driver) gets view permissions only
        $runnerPermissions = DB::table('permissions')
            ->whereIn('name', ['routes.view', 'runs.view'])
            ->pluck('id');

        foreach ($runnerPermissions as $permissionId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $runnerRole->id,
                'permission_id' => $permissionId,
            ]);
        }

        // Shop Manager gets view permissions
        if ($shopManagerRole) {
            $shopManagerPermissions = DB::table('permissions')
                ->whereIn('name', ['routes.view', 'runs.view'])
                ->pluck('id');

            foreach ($shopManagerPermissions as $permissionId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $shopManagerRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $this->command->info('Permissions assigned to roles successfully!');
    }
}
