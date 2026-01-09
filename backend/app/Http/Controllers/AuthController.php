<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|in:super_admin,ops_admin,dispatcher,shop_manager,parts_manager,runner_driver,technician_mobile,read_only',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
            'employee_id' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'preferred_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'pin_code' => 'nullable|string|max:255',
            'home_shop' => 'nullable|string|max:255',
            'home_location_id' => 'nullable|exists:service_locations,id',
            'personal_email' => 'nullable|string|email|max:255',
            'slack_id' => 'nullable|string|max:255',
            'dext_email' => 'nullable|string|email|max:255',
            'address' => 'nullable|string',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:255',
            'paytype' => 'nullable|string|max:255',
        ]);

        // Validate home_location_id is a shop type if provided
        if ($request->has('home_location_id') && $request->home_location_id) {
            $location = \App\Models\ServiceLocation::find($request->home_location_id);
            if ($location && $location->location_type !== 'fixed_shop') {
                return response()->json([
                    'message' => 'Home location must be a fixed shop',
                    'errors' => ['home_location_id' => ['The selected location must be a fixed shop.']]
                ], 422);
            }
        }

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'read_only',
            'employee_id' => $request->employee_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'preferred_name' => $request->preferred_name,
            'phone_number' => $request->phone_number,
            'pin_code' => $request->pin_code,
            'home_shop' => $request->home_shop,
            'home_location_id' => $request->home_location_id,
            'personal_email' => $request->personal_email,
            'slack_id' => $request->slack_id,
            'dext_email' => $request->dext_email,
            'address' => $request->address,
            'address_line_1' => $request->address_line_1,
            'address_line_2' => $request->address_line_2,
            'city' => $request->city,
            'state' => $request->state,
            'zip' => $request->zip,
            'paytype' => $request->paytype,
        ]);

        // Sync roles if provided
        if ($request->has('role_ids') && is_array($request->role_ids) && count($request->role_ids) > 0) {
            $user->syncRoles($request->role_ids);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Load relationships for response
        $user->load(['roles', 'homeLocation']);
        $userData = $user->toArray();
        $userData['role_ids'] = $user->roles->pluck('id')->toArray();

        return response()->json([
            'user' => $userData,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required',
        ]);

        // Check if login is email or username
        $fieldType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($fieldType, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Load homeLocation for the response
        $user->load('homeLocation');

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => $user->getAbilities(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user()->load('homeLocation');
        return response()->json([
            'user' => $user,
            'abilities' => $user->getAbilities(),
        ]);
    }

    public function users(Request $request)
    {
        $includeInactive = $request->query('include_inactive', 'false') === 'true';

        $query = User::with(['roles', 'homeLocation'])
            ->orderBy('updated_at', 'desc');

        if (!$includeInactive) {
            $query->where('active', true);
        }

        $users = $query->get()->map(function ($user) {
            $userData = $user->toArray();
            // Add role_ids array for frontend form binding
            $userData['role_ids'] = $user->roles->pluck('id')->toArray();
            return $userData;
        });

        return response()->json($users);
    }

    public function showUser(Request $request, $id)
    {
        $user = User::with(['roles', 'homeLocation'])->findOrFail($id);

        $userData = $user->toArray();
        $userData['role_ids'] = $user->roles->pluck('id')->toArray();

        return response()->json([
            'user' => $userData,
        ]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'role' => 'nullable|in:super_admin,ops_admin,dispatcher,shop_manager,parts_manager,runner_driver,technician_mobile,read_only',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
            'employee_id' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'preferred_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'pin_code' => 'nullable|string|max:255',
            'home_shop' => 'nullable|string|max:255',
            'home_location_id' => 'nullable|exists:service_locations,id',
            'personal_email' => 'nullable|string|email|max:255',
            'slack_id' => 'nullable|string|max:255',
            'dext_email' => 'nullable|string|email|max:255',
            'address' => 'nullable|string',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:255',
            'paytype' => 'nullable|string|max:255',
        ]);

        // Validate home_location_id is a shop type if provided
        if ($request->has('home_location_id') && $request->home_location_id) {
            $location = \App\Models\ServiceLocation::find($request->home_location_id);
            if ($location && $location->location_type !== 'fixed_shop') {
                return response()->json([
                    'message' => 'Home location must be a fixed shop',
                    'errors' => ['home_location_id' => ['The selected location must be a fixed shop.']]
                ], 422);
            }
        }

        $user->update($request->only([
            'username',
            'email',
            'role',
            'employee_id',
            'first_name',
            'last_name',
            'preferred_name',
            'phone_number',
            'pin_code',
            'home_shop',
            'home_location_id',
            'personal_email',
            'slack_id',
            'dext_email',
            'address',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'zip',
            'paytype',
        ]));

        // Sync roles if provided
        if ($request->has('role_ids')) {
            $user->syncRoles($request->role_ids ?? []);
        }

        // Reload user with relationships
        $user->load(['roles', 'homeLocation']);
        $userData = $user->toArray();
        $userData['role_ids'] = $user->roles->pluck('id')->toArray();

        return response()->json([
            'user' => $userData,
            'message' => 'User updated successfully',
        ]);
    }

    public function toggleUserActive(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent users from deactivating themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account',
            ], 403);
        }

        $user->active = !$user->active;
        $user->save();

        // Load relationships for response
        $user->load(['roles', 'homeLocation']);
        $userData = $user->toArray();
        $userData['role_ids'] = $user->roles->pluck('id')->toArray();

        return response()->json([
            'user' => $userData,
            'message' => $user->active ? 'User activated successfully' : 'User deactivated successfully',
        ]);
    }

    /**
     * Update user active status directly (for inline toggle)
     */
    public function updateUserStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent users from deactivating themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot change your own active status',
            ], 403);
        }

        $request->validate([
            'active' => 'required|boolean',
        ]);

        $user->active = $request->active;
        $user->save();

        // Load relationships for response
        $user->load(['roles', 'homeLocation']);
        $userData = $user->toArray();
        $userData['role_ids'] = $user->roles->pluck('id')->toArray();

        return response()->json([
            'user' => $userData,
            'message' => $user->active ? 'User activated successfully' : 'User deactivated successfully',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'preferred_name' => 'nullable|string|max:255',
        ]);

        $user->update($request->only([
            'email',
            'phone_number',
            'address',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'zip',
            'first_name',
            'last_name',
            'preferred_name',
        ]));

        return response()->json([
            'user' => $user,
            'message' => 'Profile updated successfully',
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                \Storage::disk('public')->delete($user->avatar);
            }

            $avatarPath = $request->file('avatar')->store('avatars', 'public');

            $user->update([
                'avatar' => $avatarPath,
            ]);

            return response()->json([
                'user' => $user,
                'avatar_url' => \Storage::url($avatarPath),
                'message' => 'Avatar uploaded successfully',
            ]);
        }

        return response()->json([
            'message' => 'No avatar file provided',
        ], 422);
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
            \Storage::disk('public')->delete($user->avatar);
        }

        $user->update([
            'avatar' => null,
        ]);

        return response()->json([
            'user' => $user,
            'message' => 'Avatar deleted successfully',
        ]);
    }

    /**
     * Upload avatar for a specific user (admin only)
     */
    public function uploadUserAvatar(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
                \Storage::disk('public')->delete($user->avatar);
            }

            $avatarPath = $request->file('avatar')->store('avatars', 'public');

            $user->update([
                'avatar' => $avatarPath,
            ]);

            // Load relationships for response
            $user->load(['roles', 'homeLocation']);
            $userData = $user->toArray();
            $userData['role_ids'] = $user->roles->pluck('id')->toArray();

            return response()->json([
                'user' => $userData,
                'avatar_url' => \Storage::url($avatarPath),
                'message' => 'Avatar uploaded successfully',
            ]);
        }

        return response()->json([
            'message' => 'No avatar file provided',
        ], 422);
    }

    /**
     * Set a preset avatar for a specific user (admin only)
     */
    public function setUserPresetAvatar(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'preset' => 'required|string|max:100',
        ]);

        // Delete old custom avatar if exists
        if ($user->avatar && \Storage::disk('public')->exists($user->avatar)) {
            \Storage::disk('public')->delete($user->avatar);
        }

        // Store preset identifier (prefixed to distinguish from uploaded files)
        $user->update([
            'avatar' => 'preset:' . $request->preset,
        ]);

        // Load relationships for response
        $user->load(['roles', 'homeLocation']);
        $userData = $user->toArray();
        $userData['role_ids'] = $user->roles->pluck('id')->toArray();

        return response()->json([
            'user' => $userData,
            'message' => 'Avatar updated successfully',
        ]);
    }

    /**
     * Delete avatar for a specific user (admin only)
     */
    public function deleteUserAvatar(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->avatar && !str_starts_with($user->avatar, 'preset:') && \Storage::disk('public')->exists($user->avatar)) {
            \Storage::disk('public')->delete($user->avatar);
        }

        $user->update([
            'avatar' => null,
        ]);

        // Load relationships for response
        $user->load(['roles', 'homeLocation']);
        $userData = $user->toArray();
        $userData['role_ids'] = $user->roles->pluck('id')->toArray();

        return response()->json([
            'user' => $userData,
            'message' => 'Avatar deleted successfully',
        ]);
    }
}
