<?php

namespace App\Services\Python;

/**
 * Response wrapper for Python CSV processor results.
 */
class PythonCsvResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly array $data,
        public readonly int $rowCount,
        public readonly int $originalCount,
        public readonly ?string $csv,
        public readonly array $columns,
        public readonly bool $truncated,
        public readonly ?string $error,
        public readonly array $raw
    ) {}

    /**
     * Create from successful Python script output.
     */
    public static function fromPythonOutput(array $output): self
    {
        return new self(
            success: $output['success'] ?? false,
            data: $output['data'] ?? [],
            rowCount: $output['row_count'] ?? 0,
            originalCount: $output['original_count'] ?? 0,
            csv: $output['csv'] ?? null,
            columns: $output['columns'] ?? [],
            truncated: $output['truncated'] ?? false,
            error: $output['error'] ?? null,
            raw: $output
        );
    }

    /**
     * Create error response.
     */
    public static function error(string $message): self
    {
        return new self(
            success: false,
            data: [],
            rowCount: 0,
            originalCount: 0,
            csv: null,
            columns: [],
            truncated: false,
            error: $message,
            raw: []
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isError(): bool
    {
        return !$this->success;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getCsv(): ?string
    {
        return $this->csv;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'row_count' => $this->rowCount,
            'original_count' => $this->originalCount,
            'csv' => $this->csv,
            'columns' => $this->columns,
            'truncated' => $this->truncated,
            'error' => $this->error,
        ];
    }
}
