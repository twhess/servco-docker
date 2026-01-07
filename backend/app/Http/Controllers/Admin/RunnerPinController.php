<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin controller for managing runner PINs.
 */
class RunnerPinController extends Controller
{
    /**
     * Set or reset a runner's PIN.
     */
    public function setPin(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string|min:4|max:8|regex:/^\d+$/',
        ], [
            'pin.regex' => 'PIN must contain only digits.',
        ]);

        // Check if user has runner role
        if (!$user->isRunner()) {
            return response()->json([
                'message' => 'User must have runner_driver role to use PIN authentication.',
            ], 422);
        }

        $user->setPin($request->input('pin'));

        Log::info('Admin: PIN set for user', [
            'admin_id' => $request->user()->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);

        return response()->json([
            'message' => 'PIN set successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'pin_enabled' => $user->pin_enabled,
            ],
        ]);
    }

    /**
     * Disable a runner's PIN.
     */
    public function disablePin(Request $request, User $user): JsonResponse
    {
        $user->disablePin();

        Log::info('Admin: PIN disabled for user', [
            'admin_id' => $request->user()->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);

        return response()->json([
            'message' => 'PIN disabled successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'pin_enabled' => $user->pin_enabled,
            ],
        ]);
    }

    /**
     * Unlock a locked PIN (reset failed attempts).
     */
    public function unlockPin(Request $request, User $user): JsonResponse
    {
        $user->resetPinAttempts();

        Log::info('Admin: PIN unlocked for user', [
            'admin_id' => $request->user()->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);

        return response()->json([
            'message' => 'PIN unlocked successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'pin_enabled' => $user->pin_enabled,
                'pin_locked' => false,
            ],
        ]);
    }

    /**
     * Get all runners with their PIN status.
     */
    public function index(Request $request): JsonResponse
    {
        // Get users who are runners (via roles relationship or legacy role field)
        $runners = User::where('active', true)
            ->where(function ($query) {
                $query->where('role', 'runner_driver')
                    ->orWhereHas('roles', function ($q) {
                        $q->where('name', 'runner_driver');
                    });
            })
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'has_pin' => !empty($user->pin_code),
                    'pin_enabled' => $user->pin_enabled,
                    'pin_locked' => $user->isPinLocked(),
                    'pin_failed_attempts' => $user->pin_failed_attempts,
                ];
            });

        return response()->json([
            'data' => $runners,
        ]);
    }

    /**
     * Update runner alert preferences.
     */
    public function updateAlertPreferences(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'alert_on_leave_with_open' => 'boolean',
            'alert_email_enabled' => 'boolean',
            'alert_slack_enabled' => 'boolean',
            'alert_popup_enabled' => 'boolean',
            'alert_sms_enabled' => 'boolean',
            'phone_e164' => 'nullable|string|max:20|regex:/^\+[1-9]\d{1,14}$/',
            'slack_member_id' => 'nullable|string|max:50',
        ], [
            'phone_e164.regex' => 'Phone must be in E.164 format (e.g., +15551234567).',
        ]);

        $user->update($request->only([
            'alert_on_leave_with_open',
            'alert_email_enabled',
            'alert_slack_enabled',
            'alert_popup_enabled',
            'alert_sms_enabled',
            'phone_e164',
            'slack_member_id',
        ]));

        Log::info('Admin: Alert preferences updated for user', [
            'admin_id' => $request->user()->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Alert preferences updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'alert_on_leave_with_open' => $user->alert_on_leave_with_open,
                'alert_email_enabled' => $user->alert_email_enabled,
                'alert_slack_enabled' => $user->alert_slack_enabled,
                'alert_popup_enabled' => $user->alert_popup_enabled,
                'alert_sms_enabled' => $user->alert_sms_enabled,
                'phone_e164' => $user->phone_e164,
                'slack_member_id' => $user->slack_member_id,
            ],
        ]);
    }
}
