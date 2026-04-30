<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name      = explode(' ', $notifiable->name ?? $notifiable->studio_name ?? 'there')[0];
        $planName  = $this->subscription->plan?->name ?? 'Moving Guru';
        $amount    = number_format((float) ($this->subscription->plan?->price ?? 0), 2);
        $currency  = strtoupper($this->subscription->plan?->currency ?? 'USD');
        $renewDate = $this->subscription->currentPeriodEnd?->format('d M Y') ?? 'soon';
        $manageUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            . ($notifiable->role === 'studio' ? '/studio/subscription' : '/portal/subscription');

        return (new MailMessage)
            ->subject("Your {$planName} subscription renews on {$renewDate}")
            ->greeting("Hi {$name},")
            ->line("Just a heads-up — your **{$planName}** subscription will automatically renew on **{$renewDate}**.")
            ->line("You'll be charged **{$currency} {$amount}** to the card on file.")
            ->action('Manage subscription', $manageUrl)
            ->line("If you'd like to cancel before then, you can do so from the link above.")
            ->salutation('— The Moving Guru team');
    }
}