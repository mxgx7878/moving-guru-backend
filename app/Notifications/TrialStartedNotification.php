<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent the moment a subscription starts in `trialing` status.
 * Fired by StripeService::subscribeOrSwap() right after the local row is
 * created. Tells the user when the trial ends and what'll be charged after.
 */
class TrialStartedNotification extends Notification implements ShouldQueue
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
        $trialEnds = $this->subscription->trialEndsAt?->format('d M Y') ?? 'shortly';
        $manageUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
            . ($notifiable->role === 'studio' ? '/studio/subscription' : '/portal/subscription');

        return (new MailMessage)
            ->subject("Your free trial of {$planName} has started")
            ->greeting("Hi {$name},")
            ->line("Welcome aboard — your free trial of **{$planName}** is now active.")
            ->line("Your trial runs until **{$trialEnds}**. You won't be charged until then.")
            ->line("After your trial ends, your card will be charged **{$currency} {$amount}** to continue your subscription.")
            ->action('Manage subscription', $manageUrl)
            ->line("Want to cancel before the trial ends? You can do that anytime from the link above — no charge.")
            ->line("Make the most of it — explore everything Moving Guru has to offer.")
            ->salutation('— The Moving Guru team');
    }
}