<?php

namespace App\Jobs;

use App\Models\CustomerImport;
use App\Services\CustomerImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCustomerImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to attempt the job.
     * We don't retry because partial progress is saved.
     */
    public int $tries = 1;

    /**
     * Maximum number of seconds the job can run.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * The import ID to process.
     */
    protected int $importId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $importId)
    {
        $this->importId = $importId;
    }

    /**
     * Execute the job.
     */
    public function handle(CustomerImportService $importService): void
    {
        Log::info('ProcessCustomerImportJob started', ['import_id' => $this->importId]);

        $import = CustomerImport::find($this->importId);

        if (!$import) {
            Log::error('CustomerImport not found', ['import_id' => $this->importId]);
            return;
        }

        // Don't process if already completed or failed
        if (in_array($import->status, ['completed', 'failed'])) {
            Log::info('Import already processed', [
                'import_id' => $this->importId,
                'status' => $import->status,
            ]);
            return;
        }

        $import->markProcessing();

        try {
            $importService->processImport($import);

            Log::info('ProcessCustomerImportJob completed successfully', [
                'import_id' => $this->importId,
                'created' => $import->created_count,
                'updated' => $import->updated_count,
                'merge_needed' => $import->merge_needed_count,
                'errors' => $import->error_count,
            ]);
        } catch (\Exception $e) {
            $import->markFailed($e->getMessage());

            Log::error('ProcessCustomerImportJob failed', [
                'import_id' => $this->importId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessCustomerImportJob permanently failed', [
            'import_id' => $this->importId,
            'error' => $exception->getMessage(),
        ]);

        $import = CustomerImport::find($this->importId);
        if ($import && $import->status !== 'failed') {
            $import->markFailed('Job failed: ' . $exception->getMessage());
        }
    }
}
