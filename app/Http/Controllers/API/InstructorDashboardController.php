<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\ProfileView;
use App\Models\Review;
use App\Models\SavedInstructor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InstructorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // ── KPIs ─────────────────────────────────────────────
        // NOTE: profile_views table only has `viewed_at` (no created_at)
        $totalProfileViews = ProfileView::where('viewed_user_id', $user->id)->count();
        $thisMonthViews    = ProfileView::where('viewed_user_id', $user->id)
            ->where('viewed_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        $apps = JobApplication::where('instructor_id', $user->id)
            ->where('status', '!=', 'withdrawn')
            ->get();

        $appsByStatus = $apps->groupBy('status')->map->count();

        $favouritedBy = SavedInstructor::where('instructor_id', $user->id)->count();

        $avgRating   = Review::where('reviewee_id', $user->id)->avg('rating');
        $reviewCount = Review::where('reviewee_id', $user->id)->count();

        // ── Profile views — last 6 months for the chart ──────
        $viewsByMonth = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd   = $monthStart->copy()->endOfMonth();

            $count = ProfileView::where('viewed_user_id', $user->id)
                ->whereBetween('viewed_at', [$monthStart, $monthEnd])
                ->count();

            $viewsByMonth[] = [
                'month'       => $monthStart->format('M Y'),
                'month_short' => $monthStart->format('M'),
                'views'       => $count,
            ];
        }

        // ── Applications by status (for pie/donut chart) ─────
        $applicationStatus = [
            ['name' => 'Pending',  'value' => (int) ($appsByStatus['pending']  ?? 0), 'fill' => '#F59E0B'],
            ['name' => 'Viewed',   'value' => (int) ($appsByStatus['viewed']   ?? 0), 'fill' => '#7F77DD'],
            ['name' => 'Accepted', 'value' => (int) ($appsByStatus['accepted'] ?? 0), 'fill' => '#10B981'],
            ['name' => 'Rejected', 'value' => (int) ($appsByStatus['rejected'] ?? 0), 'fill' => '#CE4F56'],
        ];

        // ── Recent activity (last 5 mixed events) ────────────
        $recentApplications = JobApplication::with(['jobListing:id,title,studio_id', 'jobListing.studio:id,name'])
            ->where('instructor_id', $user->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'job_title'  => $a->jobListing?->title,
                'studio'     => $a->jobListing?->studio?->name,
                'status'     => $a->status,
                'created_at' => $a->created_at,
            ]);

        // Recent viewers — order by viewed_at, expose as `created_at` for frontend consistency
        $recentViewers = ProfileView::with('viewer:id,name,role')
            ->where('viewed_user_id', $user->id)
            ->latest('viewed_at')
            ->take(5)
            ->get()
            ->map(fn ($v) => [
                'id'          => $v->id,
                'viewer_name' => $v->viewer?->name,
                'viewer_role' => $v->viewer?->role,
                'created_at'  => $v->viewed_at,
            ]);

        return ApiResponse::success('Dashboard fetched', [
            'kpis' => [
                'profile_views_total'      => $totalProfileViews,
                'profile_views_this_month' => $thisMonthViews,
                'applications_active'      => (int) (($appsByStatus['pending'] ?? 0) + ($appsByStatus['viewed'] ?? 0)),
                'applications_total'       => $apps->count(),
                'favourited_by_count'      => $favouritedBy,
                'rating_avg'               => $avgRating ? round($avgRating, 1) : null,
                'rating_count'             => $reviewCount,
            ],
            'profile_views_by_month' => $viewsByMonth,
            'application_status'     => $applicationStatus,
            'recent_applications'    => $recentApplications,
            'recent_viewers'         => $recentViewers,
        ]);
    }
}