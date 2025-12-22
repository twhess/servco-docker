<?php

namespace App\Console\Commands;

use App\Services\RouteGraphService;
use Illuminate\Console\Command;

class RebuildRouteGraphCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'routes:rebuild-cache
                            {--async : Run the rebuild in a background job}';

    /**
     * The console command description.
     */
    protected $description = 'Rebuild the route graph pathfinding cache';

    /**
     * Execute the console command.
     */
    public function handle(RouteGraphService $graphService): int
    {
        if ($this->option('async')) {
            $this->info('Dispatching route graph cache rebuild job...');
            \App\Jobs\RebuildRouteGraphCacheJob::dispatch();
            $this->info('Job dispatched successfully');
            return self::SUCCESS;
        }

        $this->info('Rebuilding route graph cache...');

        try {
            $startTime = microtime(true);

            $graphService->rebuildCache();

            $duration = round(microtime(true) - $startTime, 2);

            $this->info("Route graph cache rebuilt successfully in {$duration} seconds");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to rebuild route graph cache: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
