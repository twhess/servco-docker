<?php

namespace App\Jobs;

use App\Services\RouteGraphService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RebuildRouteGraphCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(RouteGraphService $graphService): void
    {
        Log::info('RebuildRouteGraphCacheJob started');

        try {
            $graphService->rebuildCache();

            Log::info('RebuildRouteGraphCacheJob completed successfully');
        } catch (\Exception $e) {
            Log::error('RebuildRouteGraphCacheJob failed', [
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
        Log::error('RebuildRouteGraphCacheJob permanently failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
