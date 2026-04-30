<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\RenewalReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendRenewalReminders extends Command
{
    protected $signature   = 'subscriptions:renewal-reminders';
    protected $description = 'Send renewal reminder emails 3 days before a subscription renews.';

    public function handle(): int
    {
        $targetDate = Carbon::now()->addDays(3)->toDateString();

        // Find active subs expiring in exactly 3 days that are not already
        // set to cancel (no point reminding if they've already cancelled).
        $subs = Subscription::with(['user', 'plan'])
            ->whereIn('status', ['active', 'trialing'])
            ->where('cancelAtPeriodEnd', false)
            ->whereDate('currentPeriodEnd', $targetDate)
            ->get();

        $sent   = 0;
        $failed = 0;

        foreach ($subs as $sub) {
            $user = $sub->user;

            if (!$user || !$user->email) {
                $this->warn("Skipping sub #{$sub->id} — no user or email.");
                continue;
            }

            try {
                $user->notify(new RenewalReminderNotification($sub));
                $sent++;
                Log::info('Renewal reminder sent', [
                    'userId' => $user->id,
                    'subId'  => $sub->id,
                    'plan'   => $sub->plan?->name,
                    'renews' => $targetDate,
                ]);
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Renewal reminder failed', [
                    'userId' => $user->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        $this->info("Renewal reminders — sent: {$sent}, failed: {$failed}");
        return Command::SUCCESS;
    }
}