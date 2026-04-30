<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\RenewalReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendRenewalReminders extends Command
{
    protected $signature   = 'subscriptions:renewal-reminders {--days=3 : Days before renewal to remind} {--debug : Show why each sub matched or skipped}';
    protected $description = 'Send renewal reminder emails N days before a subscription renews.';

    public function handle(): int
    {
        $daysAhead  = (int) $this->option('days');
        $debug      = $this->option('debug');
        $targetDate = Carbon::now()->addDays($daysAhead)->toDateString();

        $this->info("Looking for active subs renewing on: {$targetDate}");

        // Get matching subs
        $subs = Subscription::with(['user', 'plan'])
            ->whereIn('status', ['active', 'trialing'])
            ->where('cancelAtPeriodEnd', false)
            ->whereDate('currentPeriodEnd', $targetDate)
            ->get();

        if ($debug) {
            // Show ALL active subs to help diagnose why none matched
            $allActive = Subscription::with('user')
                ->whereIn('status', ['active', 'trialing'])
                ->get();

            $this->info("\n[DEBUG] All active/trialing subs in DB:");
            if ($allActive->isEmpty()) {
                $this->warn('  (none)');
            } else {
                foreach ($allActive as $s) {
                    $endDate = $s->currentPeriodEnd?->toDateString() ?? 'NULL';
                    $cancelled = $s->cancelAtPeriodEnd ? '(cancelAtPeriodEnd=1)' : '';
                    $this->line("  Sub #{$s->id} user={$s->userId} status={$s->status} ends={$endDate} {$cancelled}");
                }
            }
            $this->newLine();
        }

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
                    'userId' => $user->id, 'subId' => $sub->id,
                    'plan' => $sub->plan?->name, 'renews' => $targetDate,
                ]);
                $this->info("✓ Sent to {$user->email} (sub #{$sub->id})");
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Renewal reminder failed', [
                    'userId' => $user->id, 'error' => $e->getMessage(),
                ]);
                $this->error("✗ Failed for {$user->email}: {$e->getMessage()}");
            }
        }

        $this->info("Renewal reminders — sent: {$sent}, failed: {$failed}");
        return Command::SUCCESS;
    }
}