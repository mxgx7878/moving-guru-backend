<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public ?string $reason = null,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name      = explode(' ', $notifiable->name ?? $notifiable->studio_name ?? 'there')[0];
        $planName  = $this->subscription->plan?->name ?? 'Moving Guru';
        $updateUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            . ($notifiable->role === 'studio' ? '/studio/subscription' : '/portal/subscription');

        $message = (new MailMessage)
            ->subject("Action required — payment failed for {$planName}")
            ->greeting("Hi {$name},")
            ->line("We were unable to process your payment for your **{$planName}** subscription.");

        if ($this->reason) {
            $message->line("**Reason:** {$this->reason}");
        } else {
            $message->line('This can happen when a card expires, has insufficient funds, or billing details have changed.');
        }

        return $message
            ->line('Your account remains active for now, but please update your payment method to avoid any interruption.')
            ->action('Update payment method', $updateUrl)
            ->line('If you need help, reply to this email and our team will sort it out.')
            ->salutation('— The Moving Guru team');
    }
}