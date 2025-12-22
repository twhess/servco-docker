<?php

namespace App\Console\Commands;

use App\Services\RunSchedulerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateRunInstancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'runs:create
                            {date? : The date to create runs for (YYYY-MM-DD). Defaults to today}
                            {--all : Create runs for all active routes}';

    /**
     * The console command description.
     */
    protected $description = 'Manually create run instances for a specific date';

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

        $this->info("Creating run instances for {$date->toDateString()}...");

        try {
            // Create runs based on schedules (this is what the nightly job does)
            $createdRuns = $scheduler->createRunsForDate($date);

            $this->info("Successfully created {$createdRuns} run instances");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create run instances: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
