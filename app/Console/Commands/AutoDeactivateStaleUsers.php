<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AutoDeactivatedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * users:auto-deactivate-stale
 * ─────────────────────────────────────────────────────────────
 * Daily housekeeping. Any instructor who has had their profile
 * marked "active" but hasn't logged in for 30+ days gets their
 * profileStatus flipped to "inactive" and is sent a notification
 * email letting them know they can flip it back any time.
 *
 * The 30-day threshold mirrors the client spec; admin's inactive
 * filter uses 90 days so admins still see users who fell off in
 * the wider sense (e.g. who never logged back in to flip the
 * flag manually after this auto-flip ran).
 *
 * Studios are intentionally NOT auto-deactivated — they don't
 * have a "seeking" toggle in the same way; their status is
 * managed admin-side via the suspend/approve lifecycle.
 *
 * Idempotent: a second run on the same day finds no candidates.
 */
class AutoDeactivateStaleUsers extends Command
{
    protected $signature   = 'users:auto-deactivate-stale {--dry-run : Show what would change without writing}';
    protected $description = 'Flip instructors who haven\'t logged in for 30+ days to "inactive" and notify them.';

    public function handle(): int
    {
        $threshold = now()->subDays(30);
        $isDry     = (bool) $this->option('dry-run');

        $candidates = User::with('detail')
            ->where('role', 'instructor')
            ->where('status', 'active')
            ->where(function ($q) use ($threshold) {
                $q->where('last_login_at', '<', $threshold)
                  ->orWhereNull('last_login_at');
            })
            ->whereHas('detail', fn ($q) => $q->where('profileStatus', 'active'))
            ->get();

        $this->info(sprintf(
            'Found %d instructor%s with active "Seeking" status who haven\'t logged in for 30+ days.',
            $candidates->count(),
            $candidates->count() === 1 ? '' : 's',
        ));

        if ($isDry) {
            foreach ($candidates as $u) {
                $lastLogin = $u->last_login_at?->diffForHumans() ?? 'never';
                $this->line("  • {$u->name} <{$u->email}> — last login: {$lastLogin}");
            }
            $this->warn('Dry run — no changes written.');
            return self::SUCCESS;
        }

        $flipped = 0;
        foreach ($candidates as $user) {
            try {
                $user->detail->update(['profileStatus' => 'inactive']);

                // Queue the email — when SMTP is wired, deliveries flow.
                // Until then they sit in the queue without breaking the
                // command (assuming queue connection = sync or database).
                $user->notify(new AutoDeactivatedNotification());

                $flipped++;
            } catch (\Throwable $e) {
                Log::error('Auto-deactivate failed for user', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('Auto-deactivate sweep complete', [
            'candidates' => $candidates->count(),
            'flipped'    => $flipped,
        ]);

        $this->info("Done — flipped {$flipped} user" . ($flipped === 1 ? '' : 's') . " to inactive.");

        return self::SUCCESS;
    }
}