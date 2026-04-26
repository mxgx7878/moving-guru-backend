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