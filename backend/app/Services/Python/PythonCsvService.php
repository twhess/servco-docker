<?php

namespace App\Services\Python;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Service for calling the Python CSV processor.
 *
 * This service wraps the Python csv_processor.py script for high-performance
 * CSV operations using pandas. Designed to be easily migrated to FastAPI
 * in the future by simply swapping HTTP calls for process execution.
 *
 * Usage:
 *   $service = app(PythonCsvService::class);
 *   $result = $service->filter($csvContent, ['address' => 'Dayton']);
 */
class PythonCsvService
{
    protected string $pythonPath;
    protected string $scriptPath;
    protected int $timeout;
    protected int $maxRows;

    public function __construct()
    {
        $this->pythonPath = config('services.python.path', '/usr/bin/python3');
        $this->scriptPath = base_path('python/csv_processor.py');
        $this->timeout = config('services.python.timeout', 120);
        $this->maxRows = config('services.python.max_rows', 5000);
    }

    /**
     * Filter CSV data based on column:value pairs.
     *
     * @param string $csvContent Raw CSV string
     * @param array $filters Dict of column => search_value
     * @return PythonCsvResponse
     */
    public function filter(string $csvContent, array $filters): PythonCsvResponse
    {
        return $this->execute('filter', $csvContent, [
            '--filters' => json_encode($filters),
        ]);
    }

    /**
     * Search for a term across all columns.
     *
     * @param string $csvContent Raw CSV string
     * @param string $searchTerm Term to search for
     * @param array|null $columns Optional specific columns to search
     * @return PythonCsvResponse
     */
    public function search(string $csvContent, string $searchTerm, ?array $columns = null): PythonCsvResponse
    {
        $args = [
            '--term' => $searchTerm,
        ];

        if ($columns) {
            $args['--columns'] = implode(',', $columns);
        }

        return $this->execute('search', $csvContent, $args);
    }

    /**
     * Aggregate data with grouping.
     *
     * @param string $csvContent Raw CSV string
     * @param array $groupBy Columns to group by
     * @param array $aggregations Dict of column => agg_function (sum, count, mean, etc.)
     * @return PythonCsvResponse
     */
    public function aggregate(string $csvContent, array $groupBy, array $aggregations): PythonCsvResponse
    {
        return $this->execute('aggregate', $csvContent, [
            '--group-by' => implode(',', $groupBy),
            '--agg' => json_encode($aggregations),
        ]);
    }

    /**
     * Get summary statistics about the CSV.
     *
     * @param string $csvContent Raw CSV string
     * @return PythonCsvResponse
     */
    public function summary(string $csvContent): PythonCsvResponse
    {
        return $this->execute('summary', $csvContent);
    }

    /**
     * Prepare CSV data for Gemini analysis.
     * Optionally pre-filter the data before sending to Gemini.
     *
     * @param string $csvContent Raw CSV string
     * @param array|null $filters Optional filters to apply first
     * @param int|null $maxRows Maximum rows to include
     * @return PythonCsvResponse
     */
    public function prepareForGemini(string $csvContent, ?array $filters = null, ?int $maxRows = null): PythonCsvResponse
    {
        $args = [
            '--max-rows' => $maxRows ?? $this->maxRows,
        ];

        if ($filters) {
            $args['--filters'] = json_encode($filters);
        }

        return $this->execute('prepare', $csvContent, $args);
    }

    /**
     * Execute the Python script with given action and arguments.
     *
     * @param string $action The action to perform
     * @param string $csvContent CSV content to process
     * @param array $extraArgs Additional command line arguments
     * @return PythonCsvResponse
     */
    protected function execute(string $action, string $csvContent, array $extraArgs = []): PythonCsvResponse
    {
        if (!file_exists($this->scriptPath)) {
            Log::error('PythonCsvService: Script not found', ['path' => $this->scriptPath]);
            return PythonCsvResponse::error("Python script not found at: {$this->scriptPath}");
        }

        try {
            // Build command arguments
            $command = [
                $this->pythonPath,
                $this->scriptPath,
                '--action', $action,
                '--stdin',
                '--max-rows', (string) $this->maxRows,
            ];

            // Add extra arguments
            foreach ($extraArgs as $key => $value) {
                $command[] = $key;
                $command[] = $value;
            }

            Log::debug('PythonCsvService: Executing', [
                'action' => $action,
                'csv_size' => strlen($csvContent),
            ]);

            $process = new Process($command);
            $process->setTimeout($this->timeout);
            $process->setInput($csvContent);
            $process->run();

            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                Log::error('PythonCsvService: Process failed', [
                    'action' => $action,
                    'exit_code' => $process->getExitCode(),
                    'error' => $errorOutput,
                ]);

                return PythonCsvResponse::error("Python script failed: {$errorOutput}");
            }

            $output = $process->getOutput();
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('PythonCsvService: Invalid JSON output', [
                    'action' => $action,
                    'output' => substr($output, 0, 500),
                ]);

                return PythonCsvResponse::error('Failed to parse Python output as JSON');
            }

            Log::info('PythonCsvService: Success', [
                'action' => $action,
                'row_count' => $result['row_count'] ?? 0,
            ]);

            return PythonCsvResponse::fromPythonOutput($result);

        } catch (ProcessFailedException $e) {
            Log::error('PythonCsvService: Process exception', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return PythonCsvResponse::error("Process failed: {$e->getMessage()}");

        } catch (\Exception $e) {
            Log::error('PythonCsvService: Exception', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return PythonCsvResponse::error($e->getMessage());
        }
    }

    /**
     * Check if Python is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $process = new Process([$this->pythonPath, '--version']);
            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get Python version info.
     *
     * @return string|null
     */
    public function getVersion(): ?string
    {
        try {
            $process = new Process([$this->pythonPath, '--version']);
            $process->setTimeout(5);
            $process->run();

            if ($process->isSuccessful()) {
                return trim($process->getOutput() ?: $process->getErrorOutput());
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set custom max rows limit.
     *
     * @param int $maxRows
     * @return self
     */
    public function setMaxRows(int $maxRows): self
    {
        $this->maxRows = $maxRows;
        return $this;
    }

    /**
     * Set custom timeout.
     *
     * @param int $timeout
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }
}
