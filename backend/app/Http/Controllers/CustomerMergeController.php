<?php

namespace App\Http\Controllers;

use App\Models\CustomerMergeCandidate;
use App\Services\CustomerMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerMergeController extends Controller
{
    protected CustomerMergeService $mergeService;

    public function __construct(CustomerMergeService $mergeService)
    {
        $this->mergeService = $mergeService;
    }

    /**
     * List merge candidates with optional filters.
     *
     * GET /customer-merges
     * Query params: import_id, status (pending|merged|created_new|skipped|all), limit
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'import_id' => 'nullable|integer|exists:customer_imports,id',
            'status' => 'nullable|string|in:pending,merged,created_new,skipped,all',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $candidates = $this->mergeService->getMergeCandidates(
            importId: $validated['import_id'] ?? null,
            status: $validated['status'] ?? 'pending',
            limit: $validated['limit'] ?? 50
        );

        return response()->json([
            'data' => $candidates,
            'meta' => [
                'total' => $candidates->count(),
                'filters' => [
                    'import_id' => $validated['import_id'] ?? null,
                    'status' => $validated['status'] ?? 'pending',
                ],
            ],
        ]);
    }

    /**
     * Get a single merge candidate with comparison data.
     *
     * GET /customer-merges/{id}
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->mergeService->getCandidateWithComparison($id);

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Resolve a merge candidate.
     *
     * POST /customer-merges/{id}/resolve
     * Body: { action: 'merge'|'create_new'|'skip', field_selections?: {...} }
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:merge,create_new,skip',
            'field_selections' => 'nullable|array',
            'field_selections.*' => 'string|in:existing,incoming',
        ]);

        $candidate = CustomerMergeCandidate::findOrFail($id);

        $result = $this->mergeService->resolveMerge(
            candidate: $candidate,
            action: $validated['action'],
            fieldSelections: $validated['field_selections'] ?? [],
            user: $request->user()
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['customer'] ?? null,
        ]);
    }

    /**
     * Get summary counts for merge candidates.
     *
     * GET /customer-merges/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'import_id' => 'nullable|integer|exists:customer_imports,id',
        ]);

        $query = CustomerMergeCandidate::query();

        if (!empty($validated['import_id'])) {
            $query->where('customer_import_id', $validated['import_id']);
        }

        $counts = [
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'merged' => (clone $query)->where('status', 'merged')->count(),
            'created_new' => (clone $query)->where('status', 'created_new')->count(),
            'skipped' => (clone $query)->where('status', 'skipped')->count(),
            'total' => $query->count(),
        ];

        return response()->json([
            'data' => $counts,
        ]);
    }

    /**
     * Batch resolve multiple merge candidates.
     *
     * POST /customer-merges/batch-resolve
     * Body: { ids: [1,2,3], action: 'skip'|'create_new' }
     * Note: 'merge' is not allowed in batch as it requires field selections per candidate.
     */
    public function batchResolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer|exists:customer_merge_candidates,id',
            'action' => 'required|string|in:create_new,skip',
        ]);

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($validated['ids'] as $id) {
            $candidate = CustomerMergeCandidate::find($id);

            if (!$candidate) {
                $results['failed']++;
                $results['errors'][] = "Candidate #{$id} not found";
                continue;
            }

            $result = $this->mergeService->resolveMerge(
                candidate: $candidate,
                action: $validated['action'],
                fieldSelections: [],
                user: $request->user()
            );

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Candidate #{$id}: {$result['message']}";
            }
        }

        return response()->json([
            'message' => "Batch resolve completed: {$results['success']} successful, {$results['failed']} failed",
            'data' => $results,
        ]);
    }
}
