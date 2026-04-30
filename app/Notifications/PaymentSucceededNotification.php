<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSucceededNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name     = explode(' ', $notifiable->name ?? $notifiable->studio_name ?? 'there')[0];
        $planName = $this->payment->subscription?->plan?->name ?? 'Moving Guru';
        $amount   = number_format((float) $this->payment->amount, 2);
        $currency = strtoupper($this->payment->currency);
        $date     = $this->payment->paidAt?->format('d M Y') ?? now()->format('d M Y');

        $message = (new MailMessage)
            ->subject("Payment confirmed — {$planName} subscription")
            ->greeting("Hi {$name},")
            ->line("Your payment of **{$currency} {$amount}** for your **{$planName}** subscription was successful.")
            ->line("Payment date: {$date}")
            ->line("Invoice reference: {$this->payment->stripeInvoiceId}");

        if ($this->payment->hostedInvoiceUrl) {
            $message->action('View Invoice', $this->payment->hostedInvoiceUrl);
        }

        return $message
            ->line('Thanks for being part of Moving Guru.')
            ->salutation('— The Moving Guru team');
    }
}