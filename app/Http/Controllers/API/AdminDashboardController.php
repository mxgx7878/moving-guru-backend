<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\GrowPost;
use App\Models\JobListing;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * AdminDashboardController
 * ------------------------------------------------------------------
 *  GET /admin/dashboard/stats     — counters + month-over-month growth
 *  GET /admin/dashboard/activity  — recent signups, pending posts, jobs
 *
 * Both endpoints aggregate across the platform. Everything returned
 * matches the shape the frontend's AdminDashboard.jsx already expects,
 * so no UI changes are needed once these are live.
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

            // Placeholder until Stripe/billing is wired up. Frontend
            // handles missing keys gracefully via `?? 0`.
            'subscriptions' => [
                'active'               => 0,
                'trialing'             => 0,
                'cancelled_this_month' => 0,
            ],
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

        return ApiResponse::success('Dashboard activity', [
            'pending_grow_posts'    => $pendingGrow,
            'recent_signups'        => $recentSignups,
            'recent_jobs'           => $recentJobs,
            'recent_subscriptions'  => [],  // Empty until billing is wired
        ]);
    }


    public function revenue()
    {
        $now = Carbon::now();

        // ── 12-month breakdown (mock, trending upward) ───────────
        $months = [];
        $cumulative = 0;
        for ($i = 11; $i >= 0; $i--) {
            $month     = $now->copy()->subMonths($i);
            $base      = 600 + (11 - $i) * 180;
            $variance  = rand(-200, 280);
            $revenue   = max(0, $base + $variance);
            $cumulative += $revenue;
            $months[]  = [
                'month'          => $month->format('M Y'),
                'month_short'    => $month->format('M'),
                'year'           => (int) $month->format('Y'),
                'revenue'        => $revenue,
                'cumulative'     => $cumulative,
                'payments_count' => intval($revenue / 15),
            ];
        }

        $thisMonthRevenue = end($months)['revenue'];
        $lastMonthRevenue = $months[count($months) - 2]['revenue'];
        $totalRevenue     = $cumulative;

        $recentPayments = [
            ['id' => 1, 'user_name' => 'Aria Patel',     'plan' => 'Annual',   'amount' => 60, 'created_at' => $now->copy()->subHours(2)->toIso8601String()],
            ['id' => 2, 'user_name' => 'Studio Lumière', 'plan' => 'Monthly',  'amount' => 15, 'created_at' => $now->copy()->subHours(8)->toIso8601String()],
            ['id' => 3, 'user_name' => 'Jordan Reeves',  'plan' => '6 Months', 'amount' => 45, 'created_at' => $now->copy()->subDay()->toIso8601String()],
            ['id' => 4, 'user_name' => 'Casa de Yoga',   'plan' => 'Annual',   'amount' => 60, 'created_at' => $now->copy()->subDays(2)->toIso8601String()],
            ['id' => 5, 'user_name' => 'Marcus Wei',     'plan' => 'Monthly',  'amount' => 15, 'created_at' => $now->copy()->subDays(3)->toIso8601String()],
        ];

        return ApiResponse::success('Dashboard revenue', [
            'total_revenue'     => $totalRevenue,
            'mrr'               => $thisMonthRevenue,
            'this_month'        => $thisMonthRevenue,
            'last_month'        => $lastMonthRevenue,
            'growth'            => $this->growth($thisMonthRevenue, $lastMonthRevenue),
            'monthly_breakdown' => $months,
            'recent_payments'   => $recentPayments,
            'currency'          => 'USD',
            'mock'              => true,
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