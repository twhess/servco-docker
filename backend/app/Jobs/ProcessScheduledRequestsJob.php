<?php

namespace App\Jobs;

use App\Services\RunSchedulerService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledRequestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Carbon $date;

    /**
     * Create a new job instance.
     */
    public function __construct(?Carbon $date = null)
    {
        $this->date = $date ?? Carbon::today();
    }

    /**
     * Execute the job.
     */
    public function handle(RunSchedulerService $scheduler): void
    {
        Log::info('ProcessScheduledRequestsJob started', ['date' => $this->date->toDateString()]);

        try {
            // Process requests scheduled for this date
            $scheduler->processScheduledRequests($this->date);

            Log::info('ProcessScheduledRequestsJob completed successfully', [
                'date' => $this->date->toDateString(),
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessScheduledRequestsJob failed', [
                'date' => $this->date->toDateString(),
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
        Log::error('ProcessScheduledRequestsJob permanently failed', [
            'date' => $this->date->toDateString(),
            'error' => $exception->getMessage(),
        ]);
    }
}
