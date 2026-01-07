<?php

namespace App\Services\Slack;

/**
 * Value object representing a Slack API response.
 *
 * Provides a consistent interface for handling responses from both
 * the Bot API and Webhook API.
 */
class SlackResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageTs = null,
        public readonly ?string $channel = null,
        public readonly ?string $error = null,
        public readonly array $raw = []
    ) {}

    /**
     * Create a successful response from Slack API data.
     */
    public static function success(array $response): self
    {
        return new self(
            success: true,
            messageTs: $response['ts'] ?? $response['message']['ts'] ?? null,
            channel: $response['channel'] ?? null,
            error: null,
            raw: $response
        );
    }

    /**
     * Create an error response.
     */
    public static function error(string $message, array $response = []): self
    {
        return new self(
            success: false,
            messageTs: null,
            channel: $response['channel'] ?? null,
            error: $message,
            raw: $response
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
     * Get the message timestamp (useful for threading/reactions).
     */
    public function getTimestamp(): ?string
    {
        return $this->messageTs;
    }

    /**
     * Get the error message if present.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Convert to array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message_ts' => $this->messageTs,
            'channel' => $this->channel,
            'error' => $this->error,
        ];
    }
}
