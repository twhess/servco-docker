<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCustomerImportJob;
use App\Models\CustomerImport;
use App\Services\CustomerImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CustomerImportController extends Controller
{
    protected CustomerImportService $importService;

    public function __construct(CustomerImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * List customer imports.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerImport::with('uploader')
            ->orderBy('created_at', 'desc');

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Upload a new CSV and create an import.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:51200', // 50MB max
        ]);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();

        // Store file with timestamp
        $timestamp = now()->format('Y-m-d_His');
        $filename = $timestamp . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalFilename);
        $path = $file->storeAs('imports/customers', $filename);

        // Count rows in the CSV (for progress tracking)
        $totalRows = 0;
        try {
            $csv = \League\Csv\Reader::createFromPath(Storage::disk('local')->path($path), 'r');
            $csv->setHeaderOffset(0);
            $totalRows = count($csv);
        } catch (\Exception $e) {
            // If we can't count, that's okay - we'll update during processing
        }

        // Create import record
        $import = CustomerImport::create([
            'uploaded_by' => $request->user()->id,
            'file_path' => $path,
            'original_filename' => $originalFilename,
            'status' => 'pending',
            'total_rows' => $totalRows,
        ]);

        // Determine if we should process synchronously or via queue
        $processSync = $request->boolean('process_sync', false) || $request->boolean('sync', false);

        if ($processSync || $totalRows <= 100) {
            // Process synchronously for small files or explicit request
            $import->markProcessing();

            try {
                $this->importService->processImport($import);
            } catch (\Exception $e) {
                $import->markFailed($e->getMessage());
                return response()->json([
                    'message' => 'Import failed',
                    'error' => $e->getMessage(),
                    'data' => $import->fresh(),
                ], 500);
            }
        } else {
            // Dispatch to queue
            ProcessCustomerImportJob::dispatch($import->id);
        }

        return response()->json([
            'message' => 'Import created successfully',
            'data' => $import->fresh(),
        ], 201);
    }

    /**
     * Get import details with summary.
     */
    public function show(int $id): JsonResponse
    {
        $import = CustomerImport::with(['uploader', 'mergeCandidates' => function ($query) {
            $query->where('status', 'pending');
        }])->findOrFail($id);

        return response()->json([
            'data' => $import,
            'pending_merges' => $import->mergeCandidates->count(),
        ]);
    }

    /**
     * Get paginated rows for an import.
     */
    public function rows(Request $request, int $id): JsonResponse
    {
        $import = CustomerImport::findOrFail($id);

        $query = $import->rows()
            ->with('customer')
            ->orderBy('row_number', 'asc');

        // Action filter
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        $perPage = $request->get('per_page', 50);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Manually trigger processing for a pending import.
     */
    public function process(Request $request, int $id): JsonResponse
    {
        $import = CustomerImport::findOrFail($id);

        // Only allow processing if pending or failed
        if (!in_array($import->status, ['pending', 'failed'])) {
            return response()->json([
                'message' => 'Import cannot be processed',
                'current_status' => $import->status,
            ], 400);
        }

        // Reset counts if retrying
        if ($import->status === 'failed') {
            $import->update([
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'merge_needed_count' => 0,
                'error_count' => 0,
                'error_message' => null,
            ]);

            // Delete existing rows to avoid duplicates
            $import->rows()->delete();
        }

        $processSync = $request->boolean('sync', false);

        if ($processSync) {
            $import->markProcessing();

            try {
                $this->importService->processImport($import);
            } catch (\Exception $e) {
                $import->markFailed($e->getMessage());
                return response()->json([
                    'message' => 'Import failed',
                    'error' => $e->getMessage(),
                    'data' => $import->fresh(),
                ], 500);
            }
        } else {
            ProcessCustomerImportJob::dispatch($import->id);
        }

        return response()->json([
            'message' => 'Import processing started',
            'data' => $import->fresh(),
        ]);
    }

    /**
     * Delete an import and its file.
     */
    public function destroy(int $id): JsonResponse
    {
        $import = CustomerImport::findOrFail($id);

        // Delete the file
        if ($import->file_path && Storage::exists($import->file_path)) {
            Storage::delete($import->file_path);
        }

        // This will cascade delete rows and merge candidates
        $import->delete();

        return response()->json([
            'message' => 'Import deleted successfully',
        ]);
    }
}
