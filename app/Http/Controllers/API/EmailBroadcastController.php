<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcastEmail;
use App\Mail\BroadcastEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * EmailBroadcastController
 * ─────────────────────────────────────────────────────────────
 * Admin-only — compose and send a broadcast email to platform
 * users. Two modes:
 *   • send_test=true  — sends a single email to the admin only
 *                       (preview before going wide).
 *   • send_test=false — dispatches a queued job per recipient
 *                       across the chosen audience.
 */
class EmailBroadcastController extends Controller
{
    /**
     * POST /api/admin/emails/broadcast
     */
    public function send(Request $request)
    {
        $admin = Auth::user();

        $validator = Validator::make($request->all(), [
            'subject'   => 'required|string|max:200',
            'body'      => 'required|string|max:10000',
            'audience'  => 'required|string|in:all,instructors,studios,active,inactive',
            'send_test' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $subject  = $request->input('subject');
        $body     = $request->input('body');
        $audience = $request->input('audience');
        $isTest   = $request->boolean('send_test', false);

        // ── Test mode — send only to the admin's own email ──
        if ($isTest) {
            try {
                Mail::to($admin->email)->send(
                    new BroadcastEmail($subject, $body, $admin->name ?? 'Admin')
                );

                return ApiResponse::success('Test email sent to your address', [
                    'recipient' => $admin->email,
                ]);
            } catch (\Throwable $e) {
                Log::error('Broadcast test send failed', [
                    'admin_id' => $admin->id,
                    'error'    => $e->getMessage(),
                ]);
                return ApiResponse::error(
                    'Could not send test email — check mail configuration.',
                    ['error' => $e->getMessage()],
                    500,
                );
            }
        }

        // ── Real broadcast ── queue one job per recipient
        $recipients = $this->resolveAudience($audience);
        $count      = 0;

        foreach ($recipients->lazy() as $user) {
            SendBroadcastEmail::dispatch($user->id, $subject, $body);
            $count++;
        }

        Log::info('Broadcast queued', [
            'admin_id' => $admin->id,
            'audience' => $audience,
            'count'    => $count,
            'subject'  => $subject,
        ]);

        return ApiResponse::success("Broadcast queued for {$count} recipient" . ($count === 1 ? '' : 's'), [
            'queued_count' => $count,
            'audience'     => $audience,
        ]);
    }

    /**
     * GET /api/admin/emails/audience-counts
     *
     * Used by the admin form to show "this will send to X users"
     * before they hit the broadcast button.
     */
    public function audienceCounts()
    {
        return ApiResponse::success('Audience counts', [
            'all'         => User::whereIn('role', ['instructor', 'studio'])->count(),
            'instructors' => User::where('role', 'instructor')->count(),
            'studios'     => User::where('role', 'studio')->count(),
            'active'      => User::whereIn('role', ['instructor', 'studio'])
                ->where('status', 'active')
                ->count(),
            'inactive'    => User::whereIn('role', ['instructor', 'studio'])
                ->where(function ($q) {
                    $q->where('status', '!=', 'active')
                      ->orWhere('last_login_at', '<', now()->subDays(90));
                })
                ->count(),
        ]);
    }

    /**
     * Convert an audience key into a query builder for users to email.
     */
    private function resolveAudience(string $audience)
    {
        $base = User::whereIn('role', ['instructor', 'studio'])
            ->whereNotNull('email');

        return match ($audience) {
            'instructors' => $base->where('role', 'instructor'),
            'studios'     => $base->where('role', 'studio'),
            'active'      => $base->where('status', 'active'),
            'inactive'    => $base->where(function ($q) {
                $q->where('status', '!=', 'active')
                  ->orWhere('last_login_at', '<', now()->subDays(90));
            }),
            'all'         => $base,
            default       => $base,
        };
    }
}