<?php

namespace App\Services;

use App\Models\PartsRequest;
use App\Services\Slack\SlackMessageBuilder;
use App\Services\Slack\SlackResponse;
use App\Services\Slack\SlackService;
use Illuminate\Support\Facades\Log;

/**
 * Parts Runner-specific Slack notifications.
 *
 * Uses the reusable SlackService internally for all Slack communication.
 */
class SlackNotificationService
{
    public function __construct(protected SlackService $slack)
    {
    }

    /**
     * Send pickup notification to Slack.
     */
    public function notifyPickup(PartsRequest $request): SlackResponse
    {
        if (!$this->slack->isConfigured()) {
            Log::warning('Slack not configured for pickup notification');
            return SlackResponse::error('Slack not configured');
        }

        $channel = $request->slack_channel ?? $this->slack->getDefaultChannel();

        $message = SlackMessageBuilder::create()
            ->channel($channel)
            ->username('Parts Runner Bot')
            ->emoji(':package:')
            ->text("*Parts Picked Up*")
            ->attachment([
                'color' => 'good',
                'fields' => [
                    [
                        'title' => 'Request #',
                        'value' => $request->reference_number,
                        'short' => true,
                    ],
                    [
                        'title' => 'Runner',
                        'value' => $request->assignedRunner->name ?? 'Unknown',
                        'short' => true,
                    ],
                    [
                        'title' => 'From',
                        'value' => $this->getOriginText($request),
                        'short' => true,
                    ],
                    [
                        'title' => 'To',
                        'value' => $this->getDestinationText($request),
                        'short' => true,
                    ],
                    [
                        'title' => 'Details',
                        'value' => $request->details,
                        'short' => false,
                    ],
                ],
                'footer' => 'Parts Runner System',
                'ts' => now()->timestamp,
            ]);

        $response = $this->slack->sendMessage($message);

        if ($response->isError()) {
            Log::error('Failed to send Slack pickup notification', [
                'error' => $response->getError(),
                'request_id' => $request->id,
            ]);
        }

        return $response;
    }

    /**
     * Send delivery notification to Slack.
     */
    public function notifyDelivery(PartsRequest $request): SlackResponse
    {
        if (!$this->slack->isConfigured()) {
            Log::warning('Slack not configured for delivery notification');
            return SlackResponse::error('Slack not configured');
        }

        $channel = $request->slack_channel ?? $this->slack->getDefaultChannel();

        $message = SlackMessageBuilder::create()
            ->channel($channel)
            ->username('Parts Runner Bot')
            ->emoji(':white_check_mark:')
            ->text("*Parts Delivered*")
            ->attachment([
                'color' => '#36a64f',
                'fields' => [
                    [
                        'title' => 'Request #',
                        'value' => $request->reference_number,
                        'short' => true,
                    ],
                    [
                        'title' => 'Runner',
                        'value' => $request->assignedRunner->name ?? 'Unknown',
                        'short' => true,
                    ],
                    [
                        'title' => 'Delivered To',
                        'value' => $this->getDestinationText($request),
                        'short' => false,
                    ],
                    [
                        'title' => 'Details',
                        'value' => $request->details,
                        'short' => false,
                    ],
                ],
                'footer' => 'Parts Runner System',
                'ts' => now()->timestamp,
            ]);

        $response = $this->slack->sendMessage($message);

        if ($response->isError()) {
            Log::error('Failed to send Slack delivery notification', [
                'error' => $response->getError(),
                'request_id' => $request->id,
            ]);
        }

        return $response;
    }

    /**
     * Send problem notification to Slack.
     */
    public function notifyProblem(PartsRequest $request, string $problemDetails): SlackResponse
    {
        if (!$this->slack->isConfigured()) {
            return SlackResponse::error('Slack not configured');
        }

        $channel = $request->slack_channel ?? $this->slack->getDefaultChannel();

        $message = SlackMessageBuilder::create()
            ->channel($channel)
            ->username('Parts Runner Bot')
            ->emoji(':warning:')
            ->text("*Problem Reported*")
            ->attachment([
                'color' => 'danger',
                'fields' => [
                    [
                        'title' => 'Request #',
                        'value' => $request->reference_number,
                        'short' => true,
                    ],
                    [
                        'title' => 'Runner',
                        'value' => $request->assignedRunner->name ?? 'Unknown',
                        'short' => true,
                    ],
                    [
                        'title' => 'Problem',
                        'value' => $problemDetails,
                        'short' => false,
                    ],
                ],
                'footer' => 'Parts Runner System',
                'ts' => now()->timestamp,
            ]);

        $response = $this->slack->sendMessage($message);

        if ($response->isError()) {
            Log::error('Failed to send Slack problem notification', [
                'error' => $response->getError(),
                'request_id' => $request->id,
            ]);
        }

        return $response;
    }

    /**
     * Send a notification when a new parts request is created.
     */
    public function notifyNewRequest(PartsRequest $request): SlackResponse
    {
        if (!$this->slack->isConfigured()) {
            return SlackResponse::error('Slack not configured');
        }

        $channel = $request->slack_channel ?? $this->slack->getDefaultChannel();

        $message = SlackMessageBuilder::create()
            ->channel($channel)
            ->username('Parts Runner Bot')
            ->emoji(':new:')
            ->text("*New Parts Request*")
            ->attachment([
                'color' => '#439FE0',
                'fields' => [
                    [
                        'title' => 'Request #',
                        'value' => $request->reference_number,
                        'short' => true,
                    ],
                    [
                        'title' => 'Priority',
                        'value' => ucfirst($request->priority ?? 'normal'),
                        'short' => true,
                    ],
                    [
                        'title' => 'From',
                        'value' => $this->getOriginText($request),
                        'short' => true,
                    ],
                    [
                        'title' => 'To',
                        'value' => $this->getDestinationText($request),
                        'short' => true,
                    ],
                    [
                        'title' => 'Details',
                        'value' => $request->details ?: 'No details provided',
                        'short' => false,
                    ],
                ],
                'footer' => 'Parts Runner System',
                'ts' => now()->timestamp,
            ]);

        return $this->slack->sendMessage($message);
    }

    /**
     * Send a notification when a request is assigned to a runner.
     */
    public function notifyAssignment(PartsRequest $request): SlackResponse
    {
        if (!$this->slack->isConfigured()) {
            return SlackResponse::error('Slack not configured');
        }

        $channel = $request->slack_channel ?? $this->slack->getDefaultChannel();

        $message = SlackMessageBuilder::create()
            ->channel($channel)
            ->username('Parts Runner Bot')
            ->emoji(':runner:')
            ->text("*Request Assigned*")
            ->attachment([
                'color' => '#2EB67D',
                'fields' => [
                    [
                        'title' => 'Request #',
                        'value' => $request->reference_number,
                        'short' => true,
                    ],
                    [
                        'title' => 'Assigned To',
                        'value' => $request->assignedRunner->name ?? 'Unknown',
                        'short' => true,
                    ],
                    [
                        'title' => 'Route',
                        'value' => $this->getOriginText($request) . ' → ' . $this->getDestinationText($request),
                        'short' => false,
                    ],
                ],
                'footer' => 'Parts Runner System',
                'ts' => now()->timestamp,
            ]);

        return $this->slack->sendMessage($message);
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
