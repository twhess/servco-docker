<?php

namespace App\Services\Google;

/**
 * Value object representing a Google API response.
 *
 * Provides a consistent interface for handling responses from
 * Google Drive, Sheets, and other Google APIs.
 */
class GoogleResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $fileId = null,
        public readonly ?string $webViewLink = null,
        public readonly ?string $downloadLink = null,
        public readonly ?array $data = null,
        public readonly ?string $error = null,
        public readonly array $raw = []
    ) {}

    /**
     * Create a successful response for file operations.
     */
    public static function file(string $fileId, ?string $webViewLink = null, ?string $downloadLink = null, array $raw = []): self
    {
        return new self(
            success: true,
            fileId: $fileId,
            webViewLink: $webViewLink,
            downloadLink: $downloadLink,
            data: null,
            error: null,
            raw: $raw
        );
    }

    /**
     * Create a successful response with data (for reads/lists).
     */
    public static function success(array $data = [], array $raw = []): self
    {
        return new self(
            success: true,
            fileId: null,
            webViewLink: null,
            downloadLink: null,
            data: $data,
            error: null,
            raw: $raw
        );
    }

    /**
     * Create an error response.
     */
    public static function error(string $message, array $raw = []): self
    {
        return new self(
            success: false,
            fileId: null,
            webViewLink: null,
            downloadLink: null,
            data: null,
            error: $message,
            raw: $raw
        );
    }

    /**
     * Check if the response was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the response was a failure.
     */
    public function isError(): bool
    {
        return !$this->success;
    }

    /**
     * Get the file ID if present.
     */
    public function getFileId(): ?string
    {
        return $this->fileId;
    }

    /**
     * Get the error message if present.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get the data array.
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * Convert to array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'file_id' => $this->fileId,
            'web_view_link' => $this->webViewLink,
            'download_link' => $this->downloadLink,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}
