<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Service Locations Module
            ['name' => 'service_locations.create', 'module' => 'service_locations', 'action' => 'create', 'display_name' => 'Create Locations', 'description' => 'Create new service locations'],
            ['name' => 'service_locations.view_all', 'module' => 'service_locations', 'action' => 'view_all', 'display_name' => 'View All Locations', 'description' => 'View all service locations'],
            ['name' => 'service_locations.update_details', 'module' => 'service_locations', 'action' => 'update_details', 'display_name' => 'Update Location Details', 'description' => 'Edit location information'],
            ['name' => 'service_locations.update_contacts', 'module' => 'service_locations', 'action' => 'update_contacts', 'display_name' => 'Update Contacts', 'description' => 'Manage location phone numbers and emails'],
            ['name' => 'service_locations.assign_user', 'module' => 'service_locations', 'action' => 'assign_user', 'display_name' => 'Assign Users', 'description' => 'Assign users to mobile locations'],
            ['name' => 'service_locations.update_status', 'module' => 'service_locations', 'action' => 'update_status', 'display_name' => 'Update Status', 'description' => 'Change location status'],
            ['name' => 'service_locations.record_position', 'module' => 'service_locations', 'action' => 'record_position', 'display_name' => 'Record Position', 'description' => 'Update GPS position'],
            ['name' => 'service_locations.delete', 'module' => 'service_locations', 'action' => 'delete', 'display_name' => 'Delete Locations', 'description' => 'Delete service locations'],

            // Parts Requests Module
            ['name' => 'parts_requests.create', 'module' => 'parts_requests', 'action' => 'create', 'display_name' => 'Create Parts Requests', 'description' => 'Create new parts requests'],
            ['name' => 'parts_requests.view_all', 'module' => 'parts_requests', 'action' => 'view_all', 'display_name' => 'View All Requests', 'description' => 'View all parts requests'],
            ['name' => 'parts_requests.assign', 'module' => 'parts_requests', 'action' => 'assign', 'display_name' => 'Assign Requests', 'description' => 'Assign runners to parts requests'],
            ['name' => 'parts_requests.update_status', 'module' => 'parts_requests', 'action' => 'update_status', 'display_name' => 'Update Status', 'description' => 'Update request status'],
            ['name' => 'parts_requests.upload_photo', 'module' => 'parts_requests', 'action' => 'upload_photo', 'display_name' => 'Upload Photos', 'description' => 'Upload photos to parts requests'],

            // Users Module
            ['name' => 'users.create', 'module' => 'users', 'action' => 'create', 'display_name' => 'Create Users', 'description' => 'Create new user accounts'],
            ['name' => 'users.view_all', 'module' => 'users', 'action' => 'view_all', 'display_name' => 'View All Users', 'description' => 'View all users in the system'],
            ['name' => 'users.update', 'module' => 'users', 'action' => 'update', 'display_name' => 'Update Users', 'description' => 'Edit user information'],
            ['name' => 'users.delete', 'module' => 'users', 'action' => 'delete', 'display_name' => 'Delete Users', 'description' => 'Delete user accounts'],
            ['name' => 'users.assign_roles', 'module' => 'users', 'action' => 'assign_roles', 'display_name' => 'Assign Roles', 'description' => 'Assign roles to users'],

            // Roles Module
            ['name' => 'roles.create', 'module' => 'roles', 'action' => 'create', 'display_name' => 'Create Roles', 'description' => 'Create new roles'],
            ['name' => 'roles.view_all', 'module' => 'roles', 'action' => 'view_all', 'display_name' => 'View All Roles', 'description' => 'View all roles'],
            ['name' => 'roles.update', 'module' => 'roles', 'action' => 'update', 'display_name' => 'Update Roles', 'description' => 'Edit role permissions'],
            ['name' => 'roles.delete', 'module' => 'roles', 'action' => 'delete', 'display_name' => 'Delete Roles', 'description' => 'Delete custom roles'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore($permission);
        }

        // Create default roles
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'ops_admin',
                'display_name' => 'Operations Administrator',
                'description' => 'Manage locations, parts requests, and users',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'dispatcher',
                'display_name' => 'Dispatcher',
                'description' => 'Assign jobs and update statuses',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'shop_manager',
                'display_name' => 'Shop Manager',
                'description' => 'Manage location details and contacts',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'parts_manager',
                'display_name' => 'Parts Manager',
                'description' => 'Manage parts requests and location contacts',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'runner_driver',
                'display_name' => 'Parts Runner Driver',
                'description' => 'Update job status and upload photos',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'technician_mobile',
                'display_name' => 'Mobile Technician',
                'description' => 'Update status and GPS position',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'read_only',
                'display_name' => 'Read Only',
                'description' => 'View-only access to the system',
                'is_system' => true,
                'is_active' => true,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore($role);
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    private function assignPermissionsToRoles(): void
    {
        $rolePermissions = [
            'super_admin' => '*', // All permissions
            'ops_admin' => [
                'service_locations.create',
                'service_locations.view_all',
                'service_locations.update_details',
                'service_locations.update_contacts',
                'service_locations.assign_user',
                'service_locations.update_status',
                'service_locations.delete',
                'parts_requests.create',
                'parts_requests.view_all',
                'parts_requests.assign',
                'parts_requests.update_status',
                'users.view_all',
                'users.create',
                'users.update',
                'users.assign_roles',
            ],
            'dispatcher' => [
                'service_locations.view_all',
                'service_locations.assign_user',
                'service_locations.update_status',
                'service_locations.record_position',
                'parts_requests.create',
                'parts_requests.view_all',
                'parts_requests.assign',
                'users.view_all',
            ],
            'shop_manager' => [
                'service_locations.update_details',
                'service_locations.update_contacts',
                'parts_requests.create',
                'parts_requests.view_all',
            ],
            'parts_manager' => [
                'service_locations.update_contacts',
                'service_locations.update_status',
                'parts_requests.create',
                'parts_requests.view_all',
                'parts_requests.update_status',
            ],
            'runner_driver' => [
                'service_locations.update_status',
                'service_locations.record_position',
                'parts_requests.create',
                'parts_requests.view_all',
                'parts_requests.update_status',
                'parts_requests.upload_photo',
            ],
            'technician_mobile' => [
                'service_locations.update_status',
                'service_locations.record_position',
                'parts_requests.create',
                'parts_requests.view_all',
            ],
            'read_only' => [
                'parts_requests.create',
                'parts_requests.view_all',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = DB::table('roles')->where('name', $roleName)->first();
            if (!$role) continue;

            if ($permissions === '*') {
                // Super admin gets all permissions
                $allPermissions = DB::table('permissions')->pluck('id');
                foreach ($allPermissions as $permissionId) {
                    DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            } else {
                foreach ($permissions as $permissionName) {
                    $permission = DB::table('permissions')->where('name', $permissionName)->first();
                    if ($permission) {
                        DB::table('role_permission')->insertOrIgnore([
                            'role_id' => $role->id,
                            'permission_id' => $permission->id,
                        ]);
                    }
                }
            }
        }
    }
}
