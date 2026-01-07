<?php

namespace App\Services\Gemini;

/**
 * Value object representing a Gemini API response.
 *
 * Provides a consistent interface for handling responses from
 * the Gemini AI API.
 */
class GeminiResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $text = null,
        public readonly ?array $data = null,
        public readonly ?string $error = null,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?string $finishReason = null,
        public readonly array $raw = []
    ) {}

    /**
     * Create a successful text generation response.
     */
    public static function text(
        string $text,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        ?string $finishReason = null,
        array $raw = []
    ): self {
        return new self(
            success: true,
            text: $text,
            data: null,
            error: null,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            finishReason: $finishReason,
            raw: $raw
        );
    }

    /**
     * Create a successful response with structured data.
     */
    public static function success(array $data = [], array $raw = []): self
    {
        return new self(
            success: true,
            text: null,
            data: $data,
            error: null,
            promptTokens: null,
            completionTokens: null,
            finishReason: null,
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
            text: null,
            data: null,
            error: $message,
            promptTokens: null,
            completionTokens: null,
            finishReason: null,
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
     * Get the generated text.
     */
    public function getText(): ?string
    {
        return $this->text;
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
     * Get total tokens used.
     */
    public function getTotalTokens(): int
    {
        return ($this->promptTokens ?? 0) + ($this->completionTokens ?? 0);
    }

    /**
     * Convert to array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'text' => $this->text,
            'data' => $this->data,
            'error' => $this->error,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->getTotalTokens(),
            'finish_reason' => $this->finishReason,
        ];
    }
}
