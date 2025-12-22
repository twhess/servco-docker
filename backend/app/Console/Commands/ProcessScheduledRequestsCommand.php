<?php

namespace App\Console\Commands;

use App\Services\RunSchedulerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessScheduledRequestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'requests:process-scheduled
                            {date? : The date to process (YYYY-MM-DD). Defaults to today}';

    /**
     * The console command description.
     */
    protected $description = 'Process parts requests scheduled for a specific date';

    /**
     * Execute the console command.
     */
    public function handle(RunSchedulerService $scheduler): int
    {
        $dateString = $this->argument('date') ?? Carbon::today()->toDateString();

        try {
            $date = Carbon::parse($dateString);
        } catch (\Exception $e) {
            $this->error("Invalid date format: {$dateString}");
            return self::FAILURE;
        }

        $this->info("Processing scheduled requests for {$date->toDateString()}...");

        try {
            $scheduler->processScheduledRequests($date);

            $this->info("Successfully processed scheduled requests");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to process scheduled requests: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
