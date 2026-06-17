<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every admin the moment a user files a report (message or
 * profile) so the team can review it from the admin dashboard.
 * Sent inline (no queue) — fires immediately during the request.
 */
class NewReportNotification extends Notification
{
    public function __construct(public Report $report) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $this->report->loadMissing(['reporter', 'reportedUser']);

        $reportedName = $this->displayName($this->report->reportedUser);
        $reporterName = $this->displayName($this->report->reporter);
        $reasonLabel  = $this->reasonLabel($this->report->reason);
        $typeLabel    = $this->report->type === 'profile' ? 'Profile' : 'Message';

        $adminName = explode(' ', $notifiable->name ?? 'Admin')[0];
        $reviewUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/admin/reports';

        $message = (new MailMessage)
            ->subject("New report — {$reasonLabel} ({$typeLabel})")
            ->greeting("Hi {$adminName},")
            ->line("A new **{$typeLabel}** report has been submitted and needs review.")
            ->line("**Reason:** {$reasonLabel}")
            ->line("**Reported user:** {$reportedName}")
            ->line("**Reported by:** {$reporterName}");

        if ($this->report->type === 'message' && !empty($this->report->reportedMessage['body'])) {
            $snippet = mb_strimwidth((string) $this->report->reportedMessage['body'], 0, 160, '…');
            $message->line("**Reported message:** \"{$snippet}\"");
        }

        if (!empty($this->report->details)) {
            $details = mb_strimwidth((string) $this->report->details, 0, 300, '…');
            $message->line("**Additional details:** {$details}");
        }

        return $message
            ->action('Review in dashboard', $reviewUrl)
            ->line('Open the Reports section to view the full conversation context and take action.')
            ->salutation('— Moving Guru');
    }

    private function displayName($user): string
    {
        if (!$user) {
            return 'Unknown user';
        }
        if ($user->role === 'studio') {
            return $user->detail?->studioName ?: ($user->name ?: 'Studio');
        }
        return $user->name ?: 'Unknown user';
    }

    private function reasonLabel(string $reason): string
    {
        return [
            'harassment'       => 'Harassment or bullying',
            'spam'             => 'Spam',
            'sexual_content'   => 'Sexual content',
            'hate_speech'      => 'Hate speech',
            'scam_fraud'       => 'Scam / fraud',
            'threats_violence' => 'Threats or violence',
            'other'            => 'Other',
        ][$reason] ?? ucfirst(str_replace('_', ' ', $reason));
    }
}