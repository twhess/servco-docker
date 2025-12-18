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
            'employee_id' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'preferred_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'pin_code' => 'nullable|string|max:255',
            'home_shop' => 'nullable|string|max:255',
            'personal_email' => 'nullable|string|email|max:255',
            'slack_id' => 'nullable|string|max:255',
            'dext_email' => 'nullable|string|email|max:255',
            'address' => 'nullable|string',
            'paytype' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'employee_id' => $request->employee_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'preferred_name' => $request->preferred_name,
            'phone_number' => $request->phone_number,
            'pin_code' => $request->pin_code,
            'home_shop' => $request->home_shop,
            'personal_email' => $request->personal_email,
            'slack_id' => $request->slack_id,
            'dext_email' => $request->dext_email,
            'address' => $request->address,
            'paytype' => $request->paytype,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
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

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
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
        return response()->json($request->user());
    }

    public function users(Request $request)
    {
        $users = User::orderBy('created_at', 'desc')->get();
        return response()->json($users);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'employee_id' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'preferred_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'pin_code' => 'nullable|string|max:255',
            'home_shop' => 'nullable|string|max:255',
            'personal_email' => 'nullable|string|email|max:255',
            'slack_id' => 'nullable|string|max:255',
            'dext_email' => 'nullable|string|email|max:255',
            'address' => 'nullable|string',
            'paytype' => 'nullable|string|max:255',
        ]);

        $user->update($request->only([
            'username',
            'email',
            'employee_id',
            'first_name',
            'last_name',
            'preferred_name',
            'phone_number',
            'pin_code',
            'home_shop',
            'personal_email',
            'slack_id',
            'dext_email',
            'address',
            'paytype',
        ]));

        return response()->json([
            'user' => $user,
            'message' => 'User updated successfully',
        ]);
    }

    public function deleteUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent users from deleting themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'preferred_name' => 'nullable|string|max:255',
        ]);

        $user->update($request->only([
            'email',
            'phone_number',
            'address',
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
}
