<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\GrowPost;
use App\Models\JobListing;
use App\Models\Payment;        // ← added: real revenue source
use App\Models\Post;
use App\Models\Subscription;   // ← added: real subscription counts
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * AdminDashboardController
 * ------------------------------------------------------------------
 *  GET /admin/dashboard/stats     — counters + month-over-month growth
 *  GET /admin/dashboard/activity  — recent signups, pending posts, jobs
 *  GET /admin/dashboard/revenue   — real revenue from the payments table
 *
 * Everything returned matches the shape the frontend's AdminDashboard.jsx
 * already expects, so no UI changes are needed.
 */
class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard/stats
     */
    public function stats()
    {
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();

        // ── Users (instructors + studios) ────────────────────────
        $instructorsTotal = User::where('role', 'instructor')->count();
        $studiosTotal     = User::where('role', 'studio')->count();

        $instructorsThisMonth = User::where('role', 'instructor')
            ->where('created_at', '>=', $thisMonthStart)->count();
        $instructorsLastMonth = User::where('role', 'instructor')
            ->whereBetween('created_at', [$lastMonthStart, $thisMonthStart])->count();

        $studiosThisMonth = User::where('role', 'studio')
            ->where('created_at', '>=', $thisMonthStart)->count();
        $studiosLastMonth = User::where('role', 'studio')
            ->whereBetween('created_at', [$lastMonthStart, $thisMonthStart])->count();

        // ── Grow posts — grouped status counts in one query ──────
        $growByStatus = GrowPost::selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        // ── Job listings ─────────────────────────────────────────
        $jobsTotal  = JobListing::count();
        $jobsActive = JobListing::where('is_active', true)->count();

        // ── Platform posts (announcements) ───────────────────────
        $postsByStatus = Post::selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        // ── Subscriptions — REAL counts (was a hardcoded placeholder) ─
        $subsByStatus = Subscription::selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $cancelledThisMonth = Subscription::where('status', 'cancelled')
            ->where('cancelledAt', '>=', $thisMonthStart)
            ->count();

        // ── Signups per month (last 6) for the User Growth chart ──
        $signupsByMonth = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd   = $monthStart->copy()->endOfMonth();

            $instructors = User::where('role', 'instructor')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();
            $studios = User::where('role', 'studio')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $signupsByMonth[] = [
                'month'       => $monthStart->format('M Y'),
                'month_short' => $monthStart->format('M'),
                'instructors' => $instructors,
                'studios'     => $studios,
                'total'       => $instructors + $studios,
            ];
        }

        return ApiResponse::success('Dashboard stats', [
            'signups_today' => User::whereIn('role', ['instructor', 'studio'])
                ->whereDate('created_at', Carbon::today())
                ->count(),

            'instructors' => [
                'total'          => $instructorsTotal,
                'new_this_month' => $instructorsThisMonth,
                'growth'         => $this->growth($instructorsThisMonth, $instructorsLastMonth),
            ],

            'studios' => [
                'total'          => $studiosTotal,
                'new_this_month' => $studiosThisMonth,
                'growth'         => $this->growth($studiosThisMonth, $studiosLastMonth),
            ],

            'grow_posts' => [
                'total'    => (int) $growByStatus->sum(),
                'pending'  => (int) ($growByStatus['pending']  ?? 0),
                'approved' => (int) ($growByStatus['approved'] ?? 0),
                'rejected' => (int) ($growByStatus['rejected'] ?? 0),
            ],

            'jobs' => [
                'total'  => $jobsTotal,
                'active' => $jobsActive,
            ],

            'platform_posts' => [
                'published' => (int) ($postsByStatus['published'] ?? 0),
                'draft'     => (int) ($postsByStatus['draft']     ?? 0),
            ],

            // Real subscription counts pulled from the subscriptions table.
            'subscriptions' => [
                'active'               => (int) ($subsByStatus['active']   ?? 0),
                'trialing'             => (int) ($subsByStatus['trialing'] ?? 0),
                'cancelled_this_month' => $cancelledThisMonth,
            ],

            // Feeds the User Growth chart. This was computed before but never
            // returned, so the chart always rendered empty — now included.
            'signups_by_month' => $signupsByMonth,
        ]);
    }

    /**
     * GET /api/admin/dashboard/activity
     */
    public function activity()
    {
        // Pending grow posts — attach author name as `posted_by` for the UI.
        $pendingGrow = GrowPost::with('user:id,name')
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($p) {
                return [
                    'id'         => $p->id,
                    'title'      => $p->title,
                    'type'       => $p->type,
                    'posted_by'  => $p->user?->name,
                    'created_at' => $p->created_at,
                ];
            });

        // Recent signups — instructors + studios only (skip admins).
        $recentSignups = User::whereIn('role', ['instructor', 'studio'])
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        // Recent job listings — flatten studio name so the frontend can
        // display `j.studio_name || j.studio?.name`.
        $recentJobs = JobListing::with('studio:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($j) {
                return [
                    'id'          => $j->id,
                    'title'       => $j->title,
                    'studio_name' => $j->studio?->name,
                    'location'    => $j->location,
                    'is_active'   => (bool) $j->is_active,
                ];
            });

        // Recent subscriptions — now that billing is wired, return real rows.
        $recentSubscriptions = Subscription::with(['user:id,name', 'plan:id,name'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($s) {
                return [
                    'id'         => $s->id,
                    'user_name'  => $s->user?->name,
                    'plan'       => $s->plan?->name,
                    'status'     => $s->status,
                    'created_at' => $s->created_at,
                ];
            });

        return ApiResponse::success('Dashboard activity', [
            'pending_grow_posts'    => $pendingGrow,
            'recent_signups'        => $recentSignups,
            'recent_jobs'           => $recentJobs,
            'recent_subscriptions'  => $recentSubscriptions,
        ]);
    }

    /**
     * GET /api/admin/dashboard/revenue
     *
     * Real revenue, computed from paid rows in the `payments` table
     * (populated by StripeService::recordPaymentFromInvoice on every
     * successful invoice). No more mock/random data.
     */
    public function revenue()
    {
        $now = Carbon::now();

        // Revenue is recognised when a payment is actually paid. Fall back to
        // created_at for any paid row that somehow lacks a paidAt timestamp.
        $paidAt = 'COALESCE(paidAt, created_at)';

        // Seed the running cumulative with everything paid BEFORE the 12-month
        // window, so the "Lifetime" view starts from the true historical total.
        $windowStart = $now->copy()->subMonths(11)->startOfMonth();
        $cumulative  = (float) Payment::where('status', 'paid')
            ->whereRaw("{$paidAt} < ?", [$windowStart->toDateTimeString()])
            ->sum('amount');

        // ── 12-month real breakdown ──────────────────────────────
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd   = $monthStart->copy()->endOfMonth();

            $base = Payment::where('status', 'paid')
                ->whereRaw("{$paidAt} BETWEEN ? AND ?", [
                    $monthStart->toDateTimeString(),
                    $monthEnd->toDateTimeString(),
                ]);

            $revenue = (float) (clone $base)->sum('amount');
            $count   = (clone $base)->count();

            $cumulative += $revenue;

            $months[] = [
                'month'          => $monthStart->format('M Y'),
                'month_short'    => $monthStart->format('M'),
                'year'           => (int) $monthStart->format('Y'),
                'revenue'        => round($revenue, 2),
                'cumulative'     => round($cumulative, 2),
                'payments_count' => $count,
            ];
        }

        $thisMonthRevenue = $months[count($months) - 1]['revenue'];
        $lastMonthRevenue = $months[count($months) - 2]['revenue'];

        // Lifetime total across ALL paid payments (any date).
        $totalRevenue = (float) Payment::where('status', 'paid')->sum('amount');

        // ── Recent successful payments ───────────────────────────
        $recentPayments = Payment::with('user:id,name')
            ->where('status', 'paid')
            ->orderByRaw("{$paidAt} DESC")
            ->take(5)
            ->get()
            ->map(function ($p) {
                return [
                    'id'         => $p->id,
                    'user_name'  => $p->user?->name ?? '—',
                    'plan'       => $p->description ?: 'Subscription',
                    'amount'     => (float) $p->amount,
                    'created_at' => optional($p->paidAt ?? $p->created_at)->toIso8601String(),
                ];
            });

        // Currency — whatever the most recent paid payment used.
        $currency = Payment::where('status', 'paid')
            ->orderByRaw("{$paidAt} DESC")
            ->value('currency') ?? 'USD';

        return ApiResponse::success('Dashboard revenue', [
            'total_revenue'     => round($totalRevenue, 2),
            // MRR proxy = current-month collected revenue. Swap for a true
            // active-subscription MRR calc later if you need strict MRR.
            'mrr'               => round($thisMonthRevenue, 2),
            'this_month'        => round($thisMonthRevenue, 2),
            'last_month'        => round($lastMonthRevenue, 2),
            'growth'            => $this->growth(
                (int) round($thisMonthRevenue),
                (int) round($lastMonthRevenue),
            ),
            'monthly_breakdown' => $months,
            'recent_payments'   => $recentPayments,
            'currency'          => strtoupper($currency),
            'mock'              => false, // real data now — hides the "demo" banner
        ]);
    }

    /**
     * Month-over-month growth as a signed integer percentage.
     * Returns 0 if last month was zero (can't divide by zero cleanly).
     */
    private function growth(int $thisMonth, int $lastMonth): int
    {
        if ($lastMonth === 0) return 0;
        return (int) round((($thisMonth - $lastMonth) / $lastMonth) * 100);
    }
}