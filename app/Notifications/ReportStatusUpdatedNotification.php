<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the user who filed a report whenever an admin changes its
 * status (reviewed / resolved / dismissed), so they know it was acted on.
 * Sent inline (no queue) — fires immediately during the request.
 */
class ReportStatusUpdatedNotification extends Notification
{
    public function __construct(public Report $report) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name       = explode(' ', $notifiable->name ?? $notifiable->studio_name ?? 'there')[0];
        $status     = $this->report->status;
        $typeLabel  = $this->report->type === 'profile' ? 'profile' : 'message';
        $messageUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            . ($notifiable->role === 'studio' ? '/studio/messages' : '/portal/messages');

        [$headline, $detail] = $this->statusCopy($status, $typeLabel);

        return (new MailMessage)
            ->subject("Update on your report — {$this->statusLabel($status)}")
            ->greeting("Hi {$name},")
            ->line($headline)
            ->line($detail)
            ->action('Go to Messages', $messageUrl)
            ->line('Thank you for helping keep Moving Guru safe.')
            ->salutation('— Moving Guru');
    }

    private function statusLabel(string $status): string
    {
        return [
            'pending'   => 'Pending',
            'reviewed'  => 'Reviewed',
            'resolved'  => 'Resolved',
            'dismissed' => 'Dismissed',
        ][$status] ?? ucfirst($status);
    }

    private function statusCopy(string $status, string $typeLabel): array
    {
        return match ($status) {
            'reviewed' => [
                "Your report on a {$typeLabel} has been reviewed by our team.",
                'Our team is looking into it and will take any necessary action.',
            ],
            'resolved' => [
                "Your report on a {$typeLabel} has been resolved.",
                'We took appropriate action based on our community guidelines. Thanks for flagging it.',
            ],
            'dismissed' => [
                "Your report on a {$typeLabel} has been reviewed and closed.",
                "After review, we didn't find a violation of our community guidelines in this case. If you believe this is a mistake, you can submit a new report with more detail.",
            ],
            default => [
                "There's an update on your report on a {$typeLabel}.",
                "Its status is now: {$this->statusLabel($status)}.",
            ],
        };
    }
}