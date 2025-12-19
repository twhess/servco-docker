<?php

namespace App\Services;

use App\Models\PartsRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackNotificationService
{
    protected $webhookUrl;
    protected $defaultChannel;

    public function __construct()
    {
        $this->webhookUrl = config('services.slack.webhook_url');
        $this->defaultChannel = config('services.slack.default_channel', '#parts-alerts');
    }

    /**
     * Send pickup notification to Slack
     */
    public function notifyPickup(PartsRequest $request): void
    {
        if (!$this->webhookUrl) {
            Log::warning('Slack webhook URL not configured');
            return;
        }

        $channel = $request->slack_channel ?? $this->defaultChannel;

        $message = [
            'channel' => $channel,
            'username' => 'Parts Runner Bot',
            'icon_emoji' => ':package:',
            'text' => "✅ *Parts Picked Up*",
            'attachments' => [[
                'color' => 'good',
                'fields' => [
                    [
                        'title' => 'Request #',
                        'value' => $request->reference_number,
                        'short' => true
                    ],
                    [
                        'title' => 'Runner',
                        'value' => $request->assignedRunner->name ?? 'Unknown',
                        'short' => true
                    ],
                    [
                        'title' => 'From',
                        'value' => $this->getOriginText($request),
                        'short' => true
                    ],
                    [
                        'title' => 'To',
                        'value' => $this->getDestinationText($request),
                        'short' => true
                    ],
                    [
                        'title' => 'Details',
                        'value' => $request->details,
                        'short' => false
                    ],
                ],
                'footer' => 'Parts Runner System',
                'ts' => now()->timestamp
            ]]
        ];

        try {
            Http::timeout(5)->post($this->webhookUrl, $message);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification', [
                'error' => $e->getMessage(),
                'request_id' => $request->id
            ]);
        }
    }

    /**
     * Send delivery notification to Slack
     */
    public function notifyDelivery(PartsRequest $request): void
    {
        if (!$this->webhookUrl) {
            Log::warning('Slack webhook URL not configured');
            return;
        }

        $channel = $request->slack_channel ?? $this->defaultChannel;

        $message = [
            'channel' => $channel,
            'username' => 'Parts Runner Bot',
            'icon_emoji' => ':white_check_mark:',
            'text' => "🎉 *Parts Delivered*",
            'attachments' => [[
                'color' => '#36a64f',
                'fields' => [
                    [
                        'title' => 'Request #',
                        'value' => $request->reference_number,
                        'short' => true
                    ],
                    [
                        'title' => 'Runner',
                        'value' => $request->assignedRunner->name ?? 'Unknown',
                        'short' => true
                    ],
                    [
                        'title' => 'Delivered To',
                        'value' => $this->getDestinationText($request),
                        'short' => false
                    ],
                    [
                        'title' => 'Details',
                        'value' => $request->details,
                        'short' => false
                    ],
                ],
                'footer' => 'Parts Runner System',
                'ts' => now()->timestamp
            ]]
        ];

        try {
            Http::timeout(5)->post($this->webhookUrl, $message);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification', [
                'error' => $e->getMessage(),
                'request_id' => $request->id
            ]);
        }
    }

    /**
     * Send problem notification to Slack
     */
    public function notifyProblem(PartsRequest $request, string $problemDetails): void
    {
        if (!$this->webhookUrl) {
            return;
        }

        $channel = $request->slack_channel ?? $this->defaultChannel;

        $message = [
            'channel' => $channel,
            'username' => 'Parts Runner Bot',
            'icon_emoji' => ':warning:',
            'text' => "⚠️ *Problem Reported*",
            'attachments' => [[
                'color' => 'danger',
                'fields' => [
                    [
                        'title' => 'Request #',
                        'value' => $request->reference_number,
                        'short' => true
                    ],
                    [
                        'title' => 'Runner',
                        'value' => $request->assignedRunner->name ?? 'Unknown',
                        'short' => true
                    ],
                    [
                        'title' => 'Problem',
                        'value' => $problemDetails,
                        'short' => false
                    ],
                ],
                'footer' => 'Parts Runner System',
                'ts' => now()->timestamp
            ]]
        ];

        try {
            Http::timeout(5)->post($this->webhookUrl, $message);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification', [
                'error' => $e->getMessage(),
                'request_id' => $request->id
            ]);
        }
    }

    protected function getOriginText(PartsRequest $request): string
    {
        if ($request->vendor_name) {
            return $request->vendor_name;
        }
        if ($request->originLocation) {
            return $request->originLocation->name;
        }
        if ($request->origin_address) {
            return $request->origin_address;
        }
        return 'Unknown';
    }

    protected function getDestinationText(PartsRequest $request): string
    {
        if ($request->customer_name) {
            return $request->customer_name;
        }
        if ($request->receivingLocation) {
            return $request->receivingLocation->name;
        }
        if ($request->customer_address) {
            return $request->customer_address;
        }
        return 'Unknown';
    }
}
