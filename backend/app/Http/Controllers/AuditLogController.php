<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Get audit logs for a specific model instance.
     *
     * @param Request $request
     * @param string $modelType - Model class name (e.g., 'User', 'PartsRequest')
     * @param int $modelId - Model ID
     * @return JsonResponse
     */
    public function show(Request $request, string $modelType, int $modelId): JsonResponse
    {
        // Map short names to full class names
        $modelMap = [
            'User' => \App\Models\User::class,
            'PartsRequest' => \App\Models\PartsRequest::class,
            'Role' => \App\Models\Role::class,
            'Permission' => \App\Models\Permission::class,
            'ServiceLocation' => \App\Models\ServiceLocation::class,
        ];

        $modelClass = $modelMap[$modelType] ?? null;

        if (!$modelClass) {
            return response()->json([
                'message' => 'Invalid model type'
            ], 400);
        }

        // Check if user has permission to view audit logs for this model
        // For now, any authenticated user can view audit logs
        // You can add more granular permissions later

        $perPage = $request->get('per_page', 20);
        $event = $request->get('event'); // Optional filter by event type

        $query = AuditLog::forModel($modelClass, $modelId)
            ->with('user:id,name,email');

        if ($event) {
            $query->ofEvent($event);
        }

        $auditLogs = $query->paginate($perPage);

        return response()->json($auditLogs);
    }

    /**
     * Get activity for a specific user.
     *
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function userActivity(Request $request, int $userId): JsonResponse
    {
        // Check if user has permission to view user activity
        // Admins can view anyone's activity, users can view their own
        $authUser = $request->user();

        if ($authUser->id !== $userId && !$authUser->hasRole(['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Unauthorized to view this user\'s activity'
            ], 403);
        }

        $perPage = $request->get('per_page', 20);
        $event = $request->get('event'); // Optional filter by event type
        $modelType = $request->get('model_type'); // Optional filter by model type

        $query = AuditLog::byUser($userId)
            ->with('user:id,name,email');

        if ($event) {
            $query->ofEvent($event);
        }

        if ($modelType) {
            $query->where('auditable_type', $modelType);
        }

        $auditLogs = $query->paginate($perPage);

        return response()->json($auditLogs);
    }

    /**
     * Get recent activity across the system (admin only).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recent(Request $request): JsonResponse
    {
        // Only admins can view system-wide activity
        $authUser = $request->user();

        if (!$authUser->hasRole(['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $perPage = $request->get('per_page', 50);
        $event = $request->get('event'); // Optional filter by event type
        $modelType = $request->get('model_type'); // Optional filter by model type
        $userId = $request->get('user_id'); // Optional filter by user

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->latest('created_at');

        if ($event) {
            $query->ofEvent($event);
        }

        if ($modelType) {
            $query->where('auditable_type', $modelType);
        }

        if ($userId) {
            $query->byUser($userId);
        }

        $auditLogs = $query->paginate($perPage);

        return response()->json($auditLogs);
    }

    /**
     * Get statistics about audit logs (admin only).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        // Only admins can view statistics
        $authUser = $request->user();

        if (!$authUser->hasRole(['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);

        $stats = [
            'total_events' => AuditLog::where('created_at', '>=', $startDate)->count(),
            'by_event_type' => AuditLog::where('created_at', '>=', $startDate)
                ->selectRaw('event, COUNT(*) as count')
                ->groupBy('event')
                ->get()
                ->pluck('count', 'event'),
            'by_model_type' => AuditLog::where('created_at', '>=', $startDate)
                ->selectRaw('auditable_type, COUNT(*) as count')
                ->groupBy('auditable_type')
                ->get()
                ->pluck('count', 'auditable_type'),
            'most_active_users' => AuditLog::where('created_at', '>=', $startDate)
                ->whereNotNull('user_id')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->with('user:id,name,email')
                ->get()
                ->map(function($log) {
                    return [
                        'user' => $log->user,
                        'action_count' => $log->count
                    ];
                }),
        ];

        return response()->json([
            'period_days' => $days,
            'start_date' => $startDate->toDateTimeString(),
            'statistics' => $stats
        ]);
    }
}
