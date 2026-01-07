<?php

namespace App\Services\Slack;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reusable Slack service for sending messages via Bot API or Webhook.
 *
 * Supports channels, DMs, threads, reactions, and Block Kit.
 * Rate limiting is built-in to respect Slack's API limits.
 *
 * Usage:
 *   $slack = app(SlackService::class);
 *   $slack->sendToChannel('#general', 'Hello!');
 *   $slack->sendDirectMessage($user->slack_id, 'Private message');
 */
class SlackService
{
    protected ?string $botToken;
    protected ?string $webhookUrl;
    protected string $defaultChannel;
    protected bool $rateLimitEnabled;
    protected int $requestsPerSecond;

    protected const API_BASE = 'https://slack.com/api';
    protected const RATE_LIMIT_CACHE_PREFIX = 'slack_rate_limit_';

    public function __construct()
    {
        $this->botToken = config('services.slack.bot_token');
        $this->webhookUrl = config('services.slack.webhook_url');
        $this->defaultChannel = config('services.slack.default_channel', '#parts-alerts');
        $this->rateLimitEnabled = config('services.slack.rate_limit.enabled', true);
        $this->requestsPerSecond = config('services.slack.rate_limit.requests_per_second', 1);
    }

    /**
     * Send a message using the builder or array payload.
     *
     * Prefers Bot API, falls back to webhook if no token.
     */
    public function sendMessage(SlackMessageBuilder|array $message): SlackResponse
    {
        $payload = $message instanceof SlackMessageBuilder ? $message->toArray() : $message;

        // Use default channel if none specified
        if (empty($payload['channel'])) {
            $payload['channel'] = $this->defaultChannel;
        }

        // Try Bot API first, fall back to webhook
        if ($this->hasBotToken()) {
            return $this->sendViaBotApi('chat.postMessage', $payload);
        }

        if ($this->hasWebhook()) {
            return $this->sendViaWebhook($payload);
        }

        Log::warning('SlackService: No bot token or webhook configured');
        return SlackResponse::error('Slack not configured');
    }

    /**
     * Send a simple message to a channel.
     */
    public function sendToChannel(string $channel, string $text, array $options = []): SlackResponse
    {
        $builder = SlackMessageBuilder::create()
            ->channel($channel)
            ->text($text);

        $this->applyOptions($builder, $options);

        return $this->sendMessage($builder);
    }

    /**
     * Send a direct message to a user by their Slack user ID.
     *
     * Requires Bot API (webhook cannot send DMs).
     */
    public function sendDirectMessage(string $userId, string $text, array $options = []): SlackResponse
    {
        if (!$this->hasBotToken()) {
            Log::warning('SlackService: Bot token required for DMs');
            return SlackResponse::error('Bot token required for direct messages');
        }

        if (empty($userId)) {
            return SlackResponse::error('User ID is required for direct messages');
        }

        $builder = SlackMessageBuilder::create()
            ->channel($userId) // DMs use user ID as channel
            ->text($text);

        $this->applyOptions($builder, $options);

        return $this->sendViaBotApi('chat.postMessage', $builder->toArray());
    }

    /**
     * Reply to a thread.
     */
    public function replyToThread(string $channel, string $threadTs, string $text, array $options = []): SlackResponse
    {
        $builder = SlackMessageBuilder::create()
            ->channel($channel)
            ->threadTs($threadTs)
            ->text($text);

        $this->applyOptions($builder, $options);

        return $this->sendMessage($builder);
    }

    /**
     * Add a reaction to a message.
     *
     * Requires Bot API.
     */
    public function addReaction(string $channel, string $timestamp, string $emoji): SlackResponse
    {
        if (!$this->hasBotToken()) {
            return SlackResponse::error('Bot token required for reactions');
        }

        // Remove colons if present (e.g., ':thumbsup:' -> 'thumbsup')
        $emoji = trim($emoji, ':');

        return $this->sendViaBotApi('reactions.add', [
            'channel' => $channel,
            'timestamp' => $timestamp,
            'name' => $emoji,
        ]);
    }

    /**
     * Remove a reaction from a message.
     *
     * Requires Bot API.
     */
    public function removeReaction(string $channel, string $timestamp, string $emoji): SlackResponse
    {
        if (!$this->hasBotToken()) {
            return SlackResponse::error('Bot token required for reactions');
        }

        $emoji = trim($emoji, ':');

        return $this->sendViaBotApi('reactions.remove', [
            'channel' => $channel,
            'timestamp' => $timestamp,
            'name' => $emoji,
        ]);
    }

    /**
     * Send a Block Kit message.
     */
    public function sendBlockKit(string $channel, array $blocks, ?string $text = null): SlackResponse
    {
        $builder = SlackMessageBuilder::create()
            ->channel($channel)
            ->blocks($blocks);

        if ($text) {
            $builder->text($text); // Fallback text for notifications
        }

        return $this->sendMessage($builder);
    }

    /**
     * Send via webhook (fallback/legacy method).
     */
    public function sendWebhook(SlackMessageBuilder|array $message): SlackResponse
    {
        $payload = $message instanceof SlackMessageBuilder ? $message->toArray() : $message;
        return $this->sendViaWebhook($payload);
    }

    /**
     * Update an existing message.
     *
     * Requires Bot API.
     */
    public function updateMessage(string $channel, string $timestamp, SlackMessageBuilder|array $message): SlackResponse
    {
        if (!$this->hasBotToken()) {
            return SlackResponse::error('Bot token required to update messages');
        }

        $payload = $message instanceof SlackMessageBuilder ? $message->toArray() : $message;
        $payload['channel'] = $channel;
        $payload['ts'] = $timestamp;

        return $this->sendViaBotApi('chat.update', $payload);
    }

    /**
     * Delete a message.
     *
     * Requires Bot API.
     */
    public function deleteMessage(string $channel, string $timestamp): SlackResponse
    {
        if (!$this->hasBotToken()) {
            return SlackResponse::error('Bot token required to delete messages');
        }

        return $this->sendViaBotApi('chat.delete', [
            'channel' => $channel,
            'ts' => $timestamp,
        ]);
    }

    /**
     * Check if the service is configured (has either token or webhook).
     */
    public function isConfigured(): bool
    {
        return $this->hasBotToken() || $this->hasWebhook();
    }

    /**
     * Check if bot token is available.
     */
    public function hasBotToken(): bool
    {
        return !empty($this->botToken);
    }

    /**
     * Check if webhook URL is available.
     */
    public function hasWebhook(): bool
    {
        return !empty($this->webhookUrl);
    }

    /**
     * Get the default channel.
     */
    public function getDefaultChannel(): string
    {
        return $this->defaultChannel;
    }

    /**
     * Send request via Slack Bot API.
     */
    protected function sendViaBotApi(string $method, array $payload): SlackResponse
    {
        $this->waitForRateLimit($method);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->botToken,
                    'Content-Type' => 'application/json; charset=utf-8',
                ])
                ->post(self::API_BASE . '/' . $method, $payload);

            $data = $response->json();

            if ($response->successful() && ($data['ok'] ?? false)) {
                return SlackResponse::success($data);
            }

            $error = $data['error'] ?? 'Unknown error';
            Log::error('SlackService: Bot API error', [
                'method' => $method,
                'error' => $error,
                'response' => $data,
            ]);

            return SlackResponse::error($error, $data);
        } catch (\Exception $e) {
            Log::error('SlackService: Exception during Bot API call', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return SlackResponse::error($e->getMessage());
        }
    }

    /**
     * Send request via Webhook.
     */
    protected function sendViaWebhook(array $payload): SlackResponse
    {
        if (!$this->webhookUrl) {
            return SlackResponse::error('Webhook URL not configured');
        }

        $this->waitForRateLimit('webhook');

        try {
            $response = Http::timeout(10)->post($this->webhookUrl, $payload);

            if ($response->successful()) {
                // Webhook returns 'ok' as plain text on success
                return SlackResponse::success([
                    'ok' => true,
                    'channel' => $payload['channel'] ?? null,
                ]);
            }

            $body = $response->body();
            Log::error('SlackService: Webhook error', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            return SlackResponse::error($body ?: 'Webhook request failed');
        } catch (\Exception $e) {
            Log::error('SlackService: Exception during webhook call', [
                'error' => $e->getMessage(),
            ]);

            return SlackResponse::error($e->getMessage());
        }
    }

    /**
     * Apply common options to builder.
     */
    protected function applyOptions(SlackMessageBuilder $builder, array $options): void
    {
        if (isset($options['username'])) {
            $builder->username($options['username']);
        }

        if (isset($options['emoji']) || isset($options['icon_emoji'])) {
            $builder->emoji($options['emoji'] ?? $options['icon_emoji']);
        }

        if (isset($options['icon_url'])) {
            $builder->iconUrl($options['icon_url']);
        }

        if (isset($options['attachments'])) {
            $builder->attachments($options['attachments']);
        }

        if (isset($options['blocks'])) {
            $builder->blocks($options['blocks']);
        }

        if (isset($options['thread_ts'])) {
            $builder->threadTs($options['thread_ts']);
        }

        if (isset($options['unfurl_links'])) {
            $builder->unfurlLinks($options['unfurl_links']);
        }

        if (isset($options['unfurl_media'])) {
            $builder->unfurlMedia($options['unfurl_media']);
        }
    }

    /**
     * Simple rate limiting using cache.
     *
     * Slack Tier 1 methods allow ~1 request per second.
     */
    protected function waitForRateLimit(string $method): void
    {
        if (!$this->rateLimitEnabled) {
            return;
        }

        $cacheKey = self::RATE_LIMIT_CACHE_PREFIX . $method;
        $waitUntil = Cache::get($cacheKey);

        if ($waitUntil && $waitUntil > now()) {
            $sleepMs = (int) ($waitUntil->diffInMilliseconds(now()));
            if ($sleepMs > 0 && $sleepMs <= 2000) {
                usleep($sleepMs * 1000);
            }
        }

        // Set next allowed time
        $interval = 1000 / max($this->requestsPerSecond, 1); // ms between requests
        Cache::put($cacheKey, now()->addMilliseconds($interval), 5);
    }
}
