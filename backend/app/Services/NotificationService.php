<?php

namespace App\Services;

use App\Models\PartsRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Notify when part is marked as Not Available
     */
    public function notifyNotAvailable(PartsRequest $request, string $reason): void
    {
        $message = "❌ *Part Request Not Available*\n" .
                   "Reference: `{$request->reference_number}`\n" .
                   "Location: {$request->originLocation->name}\n" .
                   "Reason: {$reason}\n" .
                   "Requester: {$request->requestedBy->name}";

        // Send Slack notification
        if ($request->slack_channel) {
            $this->sendSlackNotification($request->slack_channel, $message);
        }

        // Send email to requester
        if ($request->requestedBy && $request->requestedBy->email) {
            $this->sendEmail(
                $request->requestedBy->email,
                "Part Request Not Available - {$request->reference_number}",
                $message
            );
        }

        Log::info("Not available notification sent for request #{$request->id}");
    }

    /**
     * Notify when delivery fails (unable to deliver)
     */
    public function notifyUnableToDeliver(PartsRequest $request, string $reason): void
    {
        $message = "⚠️ *Delivery Failed*\n" .
                   "Reference: `{$request->reference_number}`\n" .
                   "Customer: {$request->customer_name}\n" .
                   "Reason: {$reason}\n" .
                   "Part is being returned to origin. Please coordinate rescheduling.";

        // Send Slack notification
        if ($request->slack_channel) {
            $this->sendSlackNotification($request->slack_channel, $message);
        }

        // Send email to requester
        if ($request->requestedBy && $request->requestedBy->email) {
            $this->sendEmail(
                $request->requestedBy->email,
                "Delivery Failed - {$request->reference_number}",
                $message
            );
        }

        // TODO: Send SMS to customer if phone number available
        if ($request->customer_phone) {
            Log::info("SMS notification needed for customer: {$request->customer_phone}");
        }

        Log::info("Unable to deliver notification sent for request #{$request->id}");
    }

    /**
     * Notify when multi-leg segments are created
     */
    public function notifySegmentCreated(PartsRequest $parent, Collection $segments): void
    {
        $segmentCount = $segments->count();
        $pathString = $this->buildPathString($segments);

        $message = "🔗 *Multi-Leg Routing Created*\n" .
                   "Reference: `{$parent->reference_number}`\n" .
                   "Segments: {$segmentCount}\n" .
                   "Path: {$pathString}\n" .
                   "Requester: {$parent->requestedBy->name}";

        // Send Slack notification to requester
        if ($parent->slack_channel) {
            $this->sendSlackNotification($parent->slack_channel, $message);
        }

        Log::info("Multi-leg notification sent for request #{$parent->id}");
    }

    /**
     * Notify runner when all tasks at a stop are complete
     */
    public function notifyStopComplete(int $runId, int $stopId): void
    {
        $message = "✅ *All Tasks Complete at Stop*\n" .
                   "You can proceed to the next stop.";

        // TODO: Send push notification to runner's mobile device
        Log::info("Stop complete notification for run #{$runId} stop #{$stopId}");
    }

    /**
     * Notify runner when run is complete
     */
    public function notifyRunComplete(int $runId): void
    {
        $message = "🎉 *Run Complete*\n" .
                   "All deliveries and pickups completed for this run.";

        // TODO: Send push notification to runner's mobile device
        Log::info("Run complete notification for run #{$runId}");
    }

    /**
     * Warn runner about incomplete tasks when trying to leave stop
     */
    public function warnIncompleteTasks(int $runId, int $stopId, int $remaining): array
    {
        return [
            'warning' => true,
            'message' => "You have {$remaining} incomplete tasks at this stop. Are you sure you want to leave?",
            'remaining_tasks' => $remaining,
        ];
    }

    /**
     * Send Slack notification
     */
    private function sendSlackNotification(string $channel, string $message): void
    {
        $webhookUrl = config('services.slack.webhook_url');

        if (!$webhookUrl) {
            Log::warning("Slack webhook URL not configured");
            return;
        }

        try {
            Http::post($webhookUrl, [
                'channel' => $channel,
                'text' => $message,
                'username' => 'Parts Runner Bot',
                'icon_emoji' => ':truck:',
            ]);

            Log::info("Slack notification sent to {$channel}");
        } catch (\Exception $e) {
            Log::error("Failed to send Slack notification: " . $e->getMessage());
        }
    }

    /**
     * Send email notification
     */
    private function sendEmail(string $to, string $subject, string $body): void
    {
        try {
            // TODO: Implement proper email template
            Log::info("Email would be sent to {$to}: {$subject}");

            // Mail::raw($body, function ($message) use ($to, $subject) {
            //     $message->to($to)
            //             ->subject($subject);
            // });
        } catch (\Exception $e) {
            Log::error("Failed to send email: " . $e->getMessage());
        }
    }

    /**
     * Build path string from segments
     */
    private function buildPathString(Collection $segments): string
    {
        $locations = $segments->map(function ($segment) {
            return $segment->originLocation->name;
        })->toArray();

        // Add final destination
        if ($segments->isNotEmpty()) {
            $locations[] = $segments->last()->receivingLocation->name;
        }

        return implode(' → ', $locations);
    }
}
