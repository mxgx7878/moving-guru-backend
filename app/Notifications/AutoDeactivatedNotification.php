<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * AutoDeactivatedNotification
 * ─────────────────────────────────────────────────────────────
 * Sent to instructors whose "Actively Seeking" status was flipped
 * to "inactive" by the auto-deactivate sweep. Friendly tone,
 * single CTA back to the profile page where they can re-enable
 * it in two clicks.
 */
class AutoDeactivatedNotification extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name        = explode(' ', $notifiable->name)[0] ?? 'there';
        $profileUrl  = config('app.frontend_url', config('app.url')) . '/portal/profile';

        return (new MailMessage)
            ->subject('We turned off your "Actively Seeking" status')
            ->greeting("Hi {$name},")
            ->line('We noticed you haven\'t logged into Moving Guru for a little while.')
            ->line('To make sure studios only see instructors who are currently looking, we\'ve switched your profile from "Actively Seeking" to "Not Seeking" for now.')
            ->line('No worries — flipping it back is one click away.')
            ->action('Update your status', $profileUrl)
            ->line('Travel safe, and we hope to see you back soon.')
            ->salutation('— The Moving Guru team');
    }
}