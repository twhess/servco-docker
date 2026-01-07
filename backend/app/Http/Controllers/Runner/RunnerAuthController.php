<?php

namespace App\Http\Controllers\Runner;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controller for runner PIN-based authentication.
 */
class RunnerAuthController extends Controller
{
    /**
     * Authenticate a runner using their PIN.
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string|min:4|max:8|regex:/^\d+$/',
        ], [
            'pin.regex' => 'PIN must contain only digits.',
        ]);

        $pin = $request->input('pin');

        // Find user by PIN
        $user = User::findByPin($pin);

        if (!$user) {
            Log::warning('RunnerAuth: Invalid PIN attempt', [
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'pin' => ['Invalid PIN.'],
            ]);
        }

        // Check if PIN is locked
        if ($user->isPinLocked()) {
            $secondsRemaining = $user->getPinLockoutSecondsRemaining();

            Log::warning('RunnerAuth: PIN locked, attempt rejected', [
                'user_id' => $user->id,
                'seconds_remaining' => $secondsRemaining,
            ]);

            throw ValidationException::withMessages([
                'pin' => ["PIN is locked. Try again in {$secondsRemaining} seconds."],
            ]);
        }

        // Check if user can use PIN auth (has runner role and PIN enabled)
        if (!$user->canUsePinAuth()) {
            Log::warning('RunnerAuth: User cannot use PIN auth', [
                'user_id' => $user->id,
                'pin_enabled' => $user->pin_enabled,
                'is_runner' => $user->isRunner(),
            ]);

            throw ValidationException::withMessages([
                'pin' => ['PIN authentication is not enabled for this account.'],
            ]);
        }

        // Check if user is active
        if (!$user->active) {
            Log::warning('RunnerAuth: Inactive user attempted PIN login', [
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'pin' => ['This account has been deactivated.'],
            ]);
        }

        // Reset failed attempts on successful login
        $user->resetPinAttempts();

        // Create token with runner abilities
        $token = $user->createToken('runner_pin_auth', ['runner'])->plainTextToken;

        Log::info('RunnerAuth: Successful PIN login', [
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);

        $user->load('homeLocation');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'home_location' => $user->homeLocation,
                'alert_on_leave_with_open' => $user->alert_on_leave_with_open,
                'alert_popup_enabled' => $user->alert_popup_enabled,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
            'abilities' => ['runner'],
        ]);
    }

    /**
     * Logout the current runner (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        Log::info('RunnerAuth: Logout', [
            'user_id' => $user->id,
        ]);

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Get current runner's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('homeLocation');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'home_location' => $user->homeLocation,
                'alert_on_leave_with_open' => $user->alert_on_leave_with_open,
                'alert_popup_enabled' => $user->alert_popup_enabled,
            ],
            'abilities' => ['runner'],
        ]);
    }
}
