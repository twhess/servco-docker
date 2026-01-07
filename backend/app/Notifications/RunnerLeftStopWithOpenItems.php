<?php

namespace App\Notifications;

use App\Models\RouteStop;
use App\Models\RunInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notification sent when a runner leaves a stop with open (incomplete) items.
 */
class RunnerLeftStopWithOpenItems extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected RunInstance $run,
        protected RouteStop $stop,
        protected Collection $openItems
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->alert_email_enabled && $notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $stopName = $this->stop->location?->name ?? 'Unknown Stop';
        $routeName = $this->run->route?->name ?? 'Unknown Route';
        $openCount = $this->openItems->count();

        $mail = (new MailMessage)
            ->subject("Alert: {$openCount} open items left at {$stopName}")
            ->greeting("Hi {$notifiable->name},")
            ->line("You left {$stopName} with {$openCount} open item(s).")
            ->line("**Run:** #{$this->run->id}")
            ->line("**Route:** {$routeName}")
            ->line("**Stop:** {$stopName}")
            ->line('')
            ->line('**Open Items:**');

        foreach ($this->openItems->take(10) as $item) {
            $statusName = $item->status?->name ?? 'unknown';
            $mail->line("• {$item->reference_number} ({$statusName})");
        }

        if ($this->openItems->count() > 10) {
            $remaining = $this->openItems->count() - 10;
            $mail->line("...and {$remaining} more");
        }

        return $mail
            ->line('')
            ->line('Please return to complete these items or mark them as exceptions.')
            ->salutation('-- Servco Parts Runner System');
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'runner_left_with_open_items',
            'run_id' => $this->run->id,
            'stop_id' => $this->stop->id,
            'stop_name' => $this->stop->location?->name ?? 'Unknown Stop',
            'route_name' => $this->run->route?->name ?? 'Unknown Route',
            'open_count' => $this->openItems->count(),
            'open_items' => $this->openItems->take(5)->map(fn($item) => [
                'id' => $item->id,
                'reference_number' => $item->reference_number,
                'status' => $item->status?->name ?? 'unknown',
            ])->toArray(),
            'message' => "You left {$this->stop->location?->name} with {$this->openItems->count()} open item(s).",
        ];
    }
}
