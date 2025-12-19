<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Get all roles with their permissions
     */
    public function index(Request $request)
    {
        $includeInactive = $request->query('include_inactive', 'false') === 'true';

        $query = Role::with('permissions');

        if (!$includeInactive) {
            $query->where('is_active', true);
        }

        $roles = $query->orderBy('name')->get();

        return response()->json($roles);
    }

    /**
     * Get single role with permissions
     */
    public function show($id)
    {
        $role = Role::with('permissions', 'users')->findOrFail($id);
        return response()->json($role);
    }

    /**
     * Create new role
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_system' => false, // Custom roles are not system roles
        ]);

        if (isset($validated['permission_ids'])) {
            $role->syncPermissions($validated['permission_ids']);
        }

        return response()->json([
            'role' => $role->load('permissions'),
            'message' => 'Role created successfully',
        ], 201);
    }

    /**
     * Update role
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $id,
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $validated['name'] ?? $role->name,
            'display_name' => $validated['display_name'] ?? $role->display_name,
            'description' => $validated['description'] ?? $role->description,
            'is_active' => $validated['is_active'] ?? $role->is_active,
        ]);

        if (isset($validated['permission_ids'])) {
            $role->syncPermissions($validated['permission_ids']);
        }

        return response()->json([
            'role' => $role->load('permissions'),
            'message' => 'Role updated successfully',
        ]);
    }

    /**
     * Delete role
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json([
                'message' => 'Cannot delete system roles',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Get all permissions grouped by module
     */
    public function permissions()
    {
        $permissions = Permission::orderBy('module')->orderBy('action')->get();

        $grouped = $permissions->groupBy('module')->map(function ($items) {
            return $items->values();
        });

        return response()->json($grouped);
    }

    /**
     * Assign roles to user
     */
    public function assignToUser(Request $request, $userId)
    {
        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user = \App\Models\User::findOrFail($userId);
        $user->syncRoles($validated['role_ids']);

        return response()->json([
            'user' => $user->load('roles'),
            'message' => 'Roles assigned successfully',
        ]);
    }
}
