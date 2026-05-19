<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sent ~3 days before a trial ends, triggered by Stripe's
 * customer.subscription.trial_will_end webhook.
 * Last chance reminder before card is charged.
 */
class TrialEndingNotification extends Notification implements ShouldQueue
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
        $trialEnds = $this->subscription->trialEndsAt?->format('d M Y') ?? 'in a few days';
        $manageUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            . ($notifiable->role === 'studio' ? '/studio/subscription' : '/portal/subscription');

        return (new MailMessage)
            ->subject("Your {$planName} trial ends on {$trialEnds}")
            ->greeting("Hi {$name},")
            ->line("Quick reminder — your free trial of **{$planName}** ends on **{$trialEnds}**.")
            ->line("On that date, your card on file will be charged **{$currency} {$amount}** and your subscription will automatically continue.")
            ->action('Manage subscription', $manageUrl)
            ->line("If you'd like to cancel before being charged, you can do that from the link above. No questions asked.")
            ->line("Otherwise, no action needed — we'll keep your account running smoothly.")
            ->salutation('— The Moving Guru team');
    }
}