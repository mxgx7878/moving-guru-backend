<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobListing;
use App\Models\SavedInstructor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * StudioDashboardController
 * ─────────────────────────────────────────────────────────────
 * GET /api/dashboard/studio
 *
 * Studio-side aggregate: KPI counters (active listings, applicants,
 * saved instructors, total jobs ever posted), 6-month application-
 * volume chart, listings-by-type breakdown, and recent activity.
 */
class StudioDashboardController extends Controller
{
    public function index(Request $request)
    {
        $studio = $request->user();

        $myJobIds = JobListing::where('studio_id', $studio->id)->pluck('id');

        // ── KPIs ─────────────────────────────────────────────
        $activeListings = JobListing::where('studio_id', $studio->id)
            ->where('is_active', true)
            ->count();
        $totalListings = $myJobIds->count();

        $totalApplicants = JobApplication::whereIn('job_listing_id', $myJobIds)
            ->where('status', '!=', 'withdrawn')
            ->count();
        $newApplicantsThisWeek = JobApplication::whereIn('job_listing_id', $myJobIds)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $savedInstructors = SavedInstructor::where('studio_id', $studio->id)->count();

        // Totally inactive instructors aren't useful as a "network" count —
        // exclude suspended/rejected from the platform-wide instructor total
        $instructorsOnPlatform = User::where('role', 'instructor')
            ->where('status', 'active')
            ->count();

        // ── Applications received — 6-month chart ────────────
        $applicationsByMonth = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd   = $monthStart->copy()->endOfMonth();

            $count = JobApplication::whereIn('job_listing_id', $myJobIds)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $applicationsByMonth[] = [
                'month'        => $monthStart->format('M Y'),
                'month_short'  => $monthStart->format('M'),
                'applications' => $count,
            ];
        }

        // ── Listing types breakdown (for the donut) ──────────
        $listings = JobListing::where('studio_id', $studio->id)->get(['id', 'type', 'types']);
        $hire = $swap = $energy = 0;
        foreach ($listings as $job) {
            $types = is_array($job->types) && count($job->types) > 0 ? $job->types : [$job->type];
            if (in_array('hire', $types))            $hire++;
            if (in_array('swap', $types))            $swap++;
            if (in_array('energy_exchange', $types)) $energy++;
        }

        $listingTypes = [
            ['name' => 'Direct Hire',     'value' => $hire,   'fill' => '#2DA4D6'],
            ['name' => 'Instructor Swap', 'value' => $swap,   'fill' => '#E89560'],
            ['name' => 'Energy Exchange', 'value' => $energy, 'fill' => '#10B981'],
        ];

        // ── Recent activity ──────────────────────────────────
        $recentApplications = JobApplication::with(['jobListing:id,title', 'instructor:id,name'])
            ->whereIn('job_listing_id', $myJobIds)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'instructor' => $a->instructor?->name,
                'job_title'  => $a->jobListing?->title,
                'status'     => $a->status,
                'created_at' => $a->created_at,
            ]);

        $activeInstructors = User::where('role', 'instructor')
            ->whereHas('detail', fn ($q) => $q->where('profileStatus', 'active'))
            ->with('detail')
            ->latest('last_login_at')
            ->take(5)
            ->get(['id', 'name', 'role']);

        return ApiResponse::success('Dashboard fetched', [
            'kpis' => [
                'active_listings'          => $activeListings,
                'total_listings'           => $totalListings,
                'applicants_total'         => $totalApplicants,
                'applicants_this_week'     => $newApplicantsThisWeek,
                'saved_instructors'        => $savedInstructors,
                'instructors_on_platform'  => $instructorsOnPlatform,
            ],
            'applications_by_month' => $applicationsByMonth,
            'listing_types'         => $listingTypes,
            'recent_applications'   => $recentApplications,
            'active_instructors'    => $activeInstructors,
        ]);
    }
}