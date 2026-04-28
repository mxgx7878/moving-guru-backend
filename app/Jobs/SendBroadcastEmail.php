<?php

namespace App\Jobs;

use App\Mail\BroadcastEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SendBroadcastEmail
 * ─────────────────────────────────────────────────────────────
 * Single-recipient send wrapped as a queued job. Dispatched once
 * per user when an admin runs a broadcast — keeps each delivery
 * isolated so one bad address doesn't fail the batch.
 *
 * Failed sends retry up to 3x with exponential backoff (10s, 30s,
 * 90s) before being marked as permanently failed and logged.
 */
class SendBroadcastEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;
    public array $backoff = [10, 30, 90];

    public function __construct(
        public int $userId,
        public string $subjectLine,
        public string $bodyText,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user || !$user->email) {
            Log::warning('Broadcast skipped — user missing or no email', ['user_id' => $this->userId]);
            return;
        }

        Mail::to($user->email)->send(
            new BroadcastEmail($this->subjectLine, $this->bodyText, $user->name ?? '')
        );

        Log::info('Broadcast sent', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'subject' => $this->subjectLine,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Broadcast send failed permanently', [
            'user_id' => $this->userId,
            'error'   => $exception->getMessage(),
        ]);
    }
}