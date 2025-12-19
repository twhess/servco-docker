# Role-Based Access Control (RBAC) Implementation Guide

## Overview

The system now supports a flexible RBAC system where users can have multiple roles, and each role can have specific permissions. This allows for granular access control across different modules.

## Database Structure

### Tables Created

1. **roles** - Stores role definitions
   - `id`, `name`, `display_name`, `description`
   - `is_system` (system roles can't be deleted)
   - `is_active`

2. **permissions** - Stores all available permissions
   - `id`, `name`, `module`, `action`, `display_name`, `description`
   - Example: `service_locations.create` (module: service_locations, action: create)

3. **role_permission** - Many-to-many relationship
   - Maps which permissions belong to which roles

4. **user_role** - Many-to-many relationship
   - Maps which roles are assigned to which users

## Available Permissions

### Service Locations Module
- `service_locations.create` - Create new service locations
- `service_locations.view_all` - View all service locations
- `service_locations.update_details` - Edit location information
- `service_locations.update_contacts` - Manage phone numbers and emails
- `service_locations.assign_user` - Assign users to mobile locations
- `service_locations.update_status` - Change location status
- `service_locations.record_position` - Update GPS position
- `service_locations.delete` - Delete service locations

### Parts Requests Module
- `parts_requests.create` - Create new parts requests
- `parts_requests.view_all` - View all parts requests
- `parts_requests.assign` - Assign runners to requests
- `parts_requests.update_status` - Update request status
- `parts_requests.upload_photo` - Upload photos to requests

### Users Module
- `users.create` - Create new user accounts
- `users.view_all` - View all users in the system
- `users.update` - Edit user information
- `users.delete` - Delete user accounts
- `users.assign_roles` - Assign roles to users

### Roles Module
- `roles.create` - Create new roles
- `roles.view_all` - View all roles
- `roles.update` - Edit role permissions
- `roles.delete` - Delete custom roles

## Default System Roles

The system comes with 8 pre-configured roles:

1. **Super Administrator** (`super_admin`)
   - All permissions

2. **Operations Administrator** (`ops_admin`)
   - Full location and parts management
   - User management (view, create, update, assign roles)

3. **Dispatcher** (`dispatcher`)
   - View all locations
   - Assign users and update statuses
   - Assign parts requests

4. **Shop Manager** (`shop_manager`)
   - Update location details and contacts
   - Create and view parts requests

5. **Parts Manager** (`parts_manager`)
   - Manage location contacts and statuses
   - Manage parts requests

6. **Parts Runner Driver** (`runner_driver`)
   - Update job status and GPS position
   - Upload photos to requests

7. **Mobile Technician** (`technician_mobile`)
   - Update status and GPS position
   - View parts requests

8. **Read Only** (`read_only`)
   - View-only access

## API Endpoints

### Roles Management

```
GET    /api/roles                     - List all roles
GET    /api/roles/{id}                - Get single role with permissions
POST   /api/roles                     - Create new role
PUT    /api/roles/{id}                - Update role
DELETE /api/roles/{id}                - Delete role
GET    /api/permissions               - Get all permissions grouped by module
POST   /api/users/{userId}/roles      - Assign roles to user
```

### Example API Calls

**Get all roles:**
```bash
GET /api/roles
```

**Create a custom role:**
```bash
POST /api/roles
{
  "name": "warehouse_manager",
  "display_name": "Warehouse Manager",
  "description": "Manages warehouse inventory and locations",
  "is_active": true,
  "permission_ids": [1, 2, 3, 5, 9, 10]
}
```

**Assign roles to a user:**
```bash
POST /api/users/3/roles
{
  "role_ids": [2, 5]  // Assign multiple roles to user ID 3
}
```

## How It Works

### Backend (Laravel)

#### User Model
- Added `roles()` relationship (belongsToMany)
- `hasRole($roleName)` - Check if user has specific role
- `hasPermission($permissionName)` - Check if user has specific permission
- `getAllPermissions()` - Get all permissions from all user's roles
- `syncRoles($roleIds)` - Assign roles to user
- `getAbilities()` - Backward compatible, checks both new roles and legacy role field

#### Role Model
- `permissions()` relationship
- `hasPermission($permissionName)` - Check if role has permission
- `getPermissionNames()` - Get array of permission names
- `syncPermissions($permissionIds)` - Assign permissions to role

### Backward Compatibility

The system maintains backward compatibility with the legacy `role` field:
- If a user has roles assigned in the new system, those are used
- If not, it falls back to the legacy `role` field
- Existing users will continue to work until migrated

## Migration Script

To migrate existing users from the legacy `role` field to the new roles system:

```bash
docker exec myapp-backend php artisan tinker

# In tinker, run:
use App\Models\User;
use App\Models\Role;

$users = User::whereNotNull('role')->get();

foreach ($users as $user) {
    $role = Role::where('name', $user->role)->first();
    if ($role) {
        $user->roles()->attach($role->id);
        echo "Migrated user {$user->username} to role {$role->display_name}\n";
    }
}
```

## Creating Custom Roles via Database

Quick script to create a custom role:

```php
use App\Models\Role;
use App\Models\Permission;

// Create the role
$role = Role::create([
    'name' => 'inventory_manager',
    'display_name' => 'Inventory Manager',
    'description' => 'Manages inventory across all locations',
    'is_system' => false,
    'is_active' => true,
]);

// Assign specific permissions
$permissions = Permission::whereIn('name', [
    'service_locations.view_all',
    'service_locations.update_details',
    'parts_requests.create',
    'parts_requests.view_all',
])->pluck('id');

$role->permissions()->attach($permissions);
```

## Frontend Implementation Notes

The frontend should:

1. **Roles Management Page**
   - List all roles with their permissions
   - Create/Edit role dialog with permission checkboxes grouped by module
   - Cannot delete system roles

2. **User Management**
   - Replace single role dropdown with multi-select for roles
   - Show assigned roles as chips
   - Display effective permissions from all roles

3. **Permission Checking**
   - Use `authStore.can('permission.name')` as before
   - The backend getAbilities() method handles the new roles system

## Best Practices

1. **Module-Based Organization**
   - Group permissions by module (service_locations, parts_requests, etc.)
   - Use consistent naming: `module.action`

2. **Role Design**
   - Create roles based on job functions, not individual permissions
   - Combine multiple roles for complex access patterns
   - Use descriptive names and descriptions

3. **System Roles**
   - Mark built-in roles as `is_system = true`
   - System roles cannot be deleted but can be edited
   - Create custom roles for specific business needs

4. **Migration Strategy**
   - Migrate users gradually
   - Test with a few users first
   - Keep legacy role field until migration complete

## Testing the System

```bash
# Verify migrations ran
docker exec myapp-backend php artisan migrate:status

# Check that roles were seeded
docker exec myapp-backend php artisan tinker
>>> \App\Models\Role::count();
>>> \App\Models\Permission::count();

# Test assigning roles to a user
>>> $user = \App\Models\User::find(3);
>>> $role = \App\Models\Role::where('name', 'dispatcher')->first();
>>> $user->roles()->attach($role->id);
>>> $user->getAllPermissions();

# Verify abilities are returned correctly
>>> $user->fresh()->getAbilities();
```

## Security Considerations

1. **Permission Checks**
   - Always check permissions on the backend, not just frontend
   - Use Laravel policies or middleware for authorization

2. **Role Assignment**
   - Restrict who can assign roles (users.assign_roles permission)
   - Prevent privilege escalation (can't assign higher roles than you have)

3. **System Roles**
   - Protect system roles from deletion
   - Audit changes to role permissions

## Next Steps

1. Build frontend RolesPage component
2. Update UsersPage to support multiple role selection
3. Add roles menu item to sidebar
4. Migrate existing users to new system
5. Remove legacy role field once fully migrated
