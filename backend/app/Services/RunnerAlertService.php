<?php

namespace App\Services;

use App\Models\PartsRequest;
use App\Models\RouteStop;
use App\Models\RunInstance;
use App\Models\User;
use App\Notifications\RunnerLeftStopWithOpenItems;
use App\Services\Slack\SlackService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Service for sending alerts when runners leave stops with open items.
 */
class RunnerAlertService
{
    public function __construct(
        protected SlackService $slackService,
        protected SmsService $smsService
    ) {}

    /**
     * Send alerts when a runner leaves a stop with open items.
     *
     * @param User $runner The runner who left
     * @param RunInstance $run The current run
     * @param RouteStop $stop The stop that was exited
     * @param Collection $openItems The open items left at the stop
     */
    public function alertLeftWithOpenItems(
        User $runner,
        RunInstance $run,
        RouteStop $stop,
        Collection $openItems
    ): void {
        // Check if runner has alerts enabled
        if (!$runner->alert_on_leave_with_open) {
            return;
        }

        $context = [
            'runner_id' => $runner->id,
            'runner_name' => $runner->name,
            'run_id' => $run->id,
            'stop_id' => $stop->id,
            'stop_name' => $stop->location?->name ?? 'Unknown Stop',
            'open_count' => $openItems->count(),
            'open_items' => $openItems->take(5)->map(fn($item) => [
                'id' => $item->id,
                'reference' => $item->reference_number,
                'status' => $item->status?->name ?? 'unknown',
            ])->toArray(),
        ];

        Log::info('RunnerAlertService: Runner left stop with open items', $context);

        // Send in-app notification (popup)
        if ($runner->alert_popup_enabled) {
            $this->sendPopupNotification($runner, $run, $stop, $openItems);
        }

        // Send email notification
        if ($runner->alert_email_enabled && $runner->email) {
            $this->sendEmailNotification($runner, $run, $stop, $openItems);
        }

        // Send Slack notification
        if ($runner->alert_slack_enabled) {
            $this->sendSlackNotification($runner, $run, $stop, $openItems);
        }

        // Send SMS notification
        if ($runner->alert_sms_enabled && $runner->phone_e164) {
            $this->sendSmsNotification($runner, $run, $stop, $openItems);
        }
    }

    /**
     * Send in-app popup notification via Laravel notifications.
     */
    protected function sendPopupNotification(
        User $runner,
        RunInstance $run,
        RouteStop $stop,
        Collection $openItems
    ): void {
        try {
            $runner->notify(new RunnerLeftStopWithOpenItems($run, $stop, $openItems));
        } catch (\Exception $e) {
            Log::error('RunnerAlertService: Failed to send popup notification', [
                'runner_id' => $runner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification.
     */
    protected function sendEmailNotification(
        User $runner,
        RunInstance $run,
        RouteStop $stop,
        Collection $openItems
    ): void {
        try {
            $stopName = $stop->location?->name ?? 'Unknown Stop';
            $openCount = $openItems->count();

            Mail::raw(
                $this->buildEmailBody($runner, $run, $stop, $openItems),
                function ($message) use ($runner, $stopName, $openCount) {
                    $message->to($runner->email)
                        ->subject("Alert: {$openCount} open items left at {$stopName}");
                }
            );

            Log::info('RunnerAlertService: Email sent', [
                'runner_id' => $runner->id,
                'email' => $runner->email,
            ]);
        } catch (\Exception $e) {
            Log::error('RunnerAlertService: Failed to send email', [
                'runner_id' => $runner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send Slack notification.
     */
    protected function sendSlackNotification(
        User $runner,
        RunInstance $run,
        RouteStop $stop,
        Collection $openItems
    ): void {
        if (!$this->slackService->isConfigured()) {
            Log::warning('RunnerAlertService: Slack not configured');
            return;
        }

        try {
            $stopName = $stop->location?->name ?? 'Unknown Stop';
            $openCount = $openItems->count();

            $message = ":warning: *{$runner->name}* left *{$stopName}* with {$openCount} open item(s).\n";
            $message .= "Run: #{$run->id} | Route: {$run->route?->name}\n";
            $message .= "Items:\n";

            foreach ($openItems->take(5) as $item) {
                $message .= "• {$item->reference_number} ({$item->status?->name})\n";
            }

            if ($openItems->count() > 5) {
                $message .= "...and " . ($openItems->count() - 5) . " more";
            }

            // Send to runner's Slack channel if specified, otherwise default
            $channel = $runner->slack_member_id
                ? "@{$runner->slack_member_id}"
                : null;

            $this->slackService->sendMessage($message, $channel);

            Log::info('RunnerAlertService: Slack notification sent', [
                'runner_id' => $runner->id,
            ]);
        } catch (\Exception $e) {
            Log::error('RunnerAlertService: Failed to send Slack notification', [
                'runner_id' => $runner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send SMS notification.
     */
    protected function sendSmsNotification(
        User $runner,
        RunInstance $run,
        RouteStop $stop,
        Collection $openItems
    ): void {
        if (!$this->smsService->isConfigured()) {
            Log::warning('RunnerAlertService: SMS (Twilio) not configured');
            return;
        }

        try {
            $stopName = $stop->location?->name ?? 'Unknown Stop';
            $openCount = $openItems->count();

            $message = "Alert: You left {$stopName} with {$openCount} open item(s). ";
            $message .= "Please return or mark as exception.";

            $this->smsService->send($runner->phone_e164, $message);

            Log::info('RunnerAlertService: SMS sent', [
                'runner_id' => $runner->id,
                'phone' => $runner->phone_e164,
            ]);
        } catch (\Exception $e) {
            Log::error('RunnerAlertService: Failed to send SMS', [
                'runner_id' => $runner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build email body content.
     */
    protected function buildEmailBody(
        User $runner,
        RunInstance $run,
        RouteStop $stop,
        Collection $openItems
    ): string {
        $stopName = $stop->location?->name ?? 'Unknown Stop';
        $routeName = $run->route?->name ?? 'Unknown Route';

        $body = "Hi {$runner->name},\n\n";
        $body .= "You left {$stopName} with {$openItems->count()} open item(s).\n\n";
        $body .= "Run: #{$run->id}\n";
        $body .= "Route: {$routeName}\n";
        $body .= "Stop: {$stopName}\n\n";
        $body .= "Open Items:\n";

        foreach ($openItems as $item) {
            $body .= "- {$item->reference_number} ({$item->status?->name})\n";
        }

        $body .= "\nPlease return to complete these items or mark them as exceptions.\n";
        $body .= "\n-- Servco Parts Runner System";

        return $body;
    }
}
