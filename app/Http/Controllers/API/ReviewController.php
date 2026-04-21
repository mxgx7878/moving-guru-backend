<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobListing;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * ReviewController
 * ------------------------------------------------------------------
 *  PUBLIC (no auth required)
 *   GET    /users/{id}/reviews           — reviews + summary for a user
 *
 *  AUTHENTICATED
 *   POST   /reviews                      — create a review
 *   DELETE /reviews/{id}                 — delete own review
 *   GET    /reviews/mine                 — reviews I've written
 *   GET    /reviews/eligible             — accepted apps I can still review
 *
 * Direction model
 * ------------------------------------------------------------------
 * Reviews are bidirectional:
 *   studio_to_instructor — a studio reviewing an instructor they hired
 *   instructor_to_studio — an instructor reviewing a studio that hired them
 *
 * The direction is derived server-side from the reviewer/reviewee roles
 * on create; callers never pass it (which prevents spoofing).
 *
 * Eligibility
 * ------------------------------------------------------------------
 * To post a review, the reviewer must have an *accepted* job application
 * connecting them to the reviewee. Specifically:
 *   studio reviewing instructor → there exists a JobApplication where
 *     that instructor was accepted on one of this studio's listings
 *   instructor reviewing studio → there exists a JobApplication where
 *     this instructor was accepted on one of that studio's listings
 *
 * Uniqueness is enforced via the unique index on
 *   (reviewer_id, reviewee_id, job_listing_id)
 * so a studio can leave one review per instructor per listing (and vice
 * versa). Posting a second review for the same tuple returns 409.
 */
class ReviewController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  PUBLIC
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/users/{id}/reviews
     *
     * Returns all reviews written about this user, optionally filtered by
     * direction. No role restriction — any user (instructor or studio) can
     * be the subject of reviews, and blindly filtering by role here would
     * 404 the opposite-role user who legitimately exists.
     *
     * Query params:
     *   direction (optional) — studio_to_instructor | instructor_to_studio
     */
    public function forUser(Request $request, $id)
    {
        // IMPORTANT: do NOT constrain by role here. Previous versions had
        //   User::where('role', 'instructor')->findOrFail($id)
        // which 404'd whenever a studio's own profile tried to load its
        // reviews. The user's role comes from the DB, not the URL.
        $user = User::find($id);
        if (!$user) {
            return ApiResponse::error('User not found.', [], 404);
        }

        $direction = $request->query('direction');
        $validDirections = ['studio_to_instructor', 'instructor_to_studio'];

        $query = Review::with(['reviewer:id,name,email,role', 'reviewer.detail', 'jobListing:id,title'])
            ->where('reviewee_id', $user->id);

        if ($direction && in_array($direction, $validDirections, true)) {
            $query->where('direction', $direction);
        }

        $reviews = $query->orderByDesc('created_at')->get();

        // Summary: count, average, distribution
        $count = $reviews->count();
        $average = $count > 0
            ? round($reviews->avg('rating'), 1)
            : 0;

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($reviews as $r) {
            $rating = (int) $r->rating;
            if (isset($distribution[$rating])) {
                $distribution[$rating]++;
            }
        }

        return ApiResponse::success('Reviews fetched', [
            'reviews' => $reviews,
            'summary' => [
                'count'        => $count,
                'average'      => $average,
                'distribution' => $distribution,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  AUTHENTICATED
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /api/reviews
     * body: { reviewee_id, rating (1-5), comment?, job_listing_id? }
     *
     * Creates a review. Direction is derived from roles; caller must not
     * pass it. Eligibility and duplicate checks run before insert.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reviewee_id'    => 'required|integer|exists:users,id',
            'rating'         => 'required|integer|min:1|max:5',
            'comment'        => 'nullable|string|max:2000',
            'job_listing_id' => 'nullable|integer|exists:job_listings,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $reviewer   = Auth::user();
        $revieweeId = (int) $request->input('reviewee_id');
        $reviewee   = User::find($revieweeId);

        if (!$reviewee) {
            return ApiResponse::error('Reviewee not found.', [], 404);
        }

        if ($reviewer->id === $reviewee->id) {
            return ApiResponse::error('You cannot review yourself.', [], 422);
        }

        // Derive direction from roles
        $direction = null;
        if ($reviewer->role === 'studio' && $reviewee->role === 'instructor') {
            $direction = 'studio_to_instructor';
        } elseif ($reviewer->role === 'instructor' && $reviewee->role === 'studio') {
            $direction = 'instructor_to_studio';
        } else {
            return ApiResponse::error(
                'Reviews can only go between a studio and an instructor.',
                [],
                422
            );
        }

        // Eligibility: must have an accepted application connecting them
        if (!$this->hasAcceptedRelationship($reviewer, $reviewee, $request->input('job_listing_id'))) {
            return ApiResponse::error(
                'You can only review someone you\'ve worked with — an accepted job application is required.',
                [],
                403
            );
        }

        // Duplicate check (belt; the unique index is braces)
        $exists = Review::where('reviewer_id', $reviewer->id)
            ->where('reviewee_id', $reviewee->id)
            ->where('job_listing_id', $request->input('job_listing_id'))
            ->exists();

        if ($exists) {
            return ApiResponse::error(
                'You\'ve already reviewed this person for this listing.',
                [],
                409
            );
        }

        try {
            $review = Review::create([
                'reviewer_id'    => $reviewer->id,
                'reviewee_id'    => $reviewee->id,
                'direction'      => $direction,
                'rating'         => (int) $request->input('rating'),
                'comment'        => $request->input('comment'),
                'job_listing_id' => $request->input('job_listing_id'),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Race condition caught by the unique index
            if ($e->getCode() === '23000') {
                return ApiResponse::error(
                    'You\'ve already reviewed this person for this listing.',
                    [],
                    409
                );
            }
            throw $e;
        }

        $review->load(['reviewer:id,name,email,role', 'reviewer.detail', 'jobListing:id,title']);

        return ApiResponse::success('Review posted', [
            'review' => $review,
        ], 201);
    }

    /**
     * DELETE /api/reviews/{id}
     * Only the original reviewer can delete. Admins can also delete
     * (future work — not gated here).
     */
    public function destroy($id)
    {
        $review = Review::find($id);
        if (!$review) {
            return ApiResponse::error('Review not found.', [], 404);
        }

        $user = Auth::user();
        if ($review->reviewer_id !== $user->id && $user->role !== 'admin') {
            return ApiResponse::error('You can only delete your own reviews.', [], 403);
        }

        $review->delete();

        return ApiResponse::success('Review deleted', [
            'id' => (int) $id,
        ]);
    }

    /**
     * GET /api/reviews/mine
     *
     * Reviews the current user has written. Used client-side to show the
     * "Reviewed" badge on hired applicants / accepted applications without
     * a second round-trip.
     */
    public function mine()
    {
        $reviews = Review::with(['reviewee:id,name,email,role', 'reviewee.detail', 'jobListing:id,title'])
            ->where('reviewer_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success('My reviews fetched', [
            'reviews' => $reviews,
        ]);
    }

    /**
     * GET /api/reviews/eligible
     *
     * Accepted job applications where the current user hasn't yet reviewed
     * the counterparty. Useful for a dashboard prompt ("You have 2 pending
     * reviews to leave"). Output shape:
     *   [{ job_listing_id, job_title, counterparty: { id, name, ... } }]
     */
    public function eligible()
    {
        $user = Auth::user();

        if ($user->role === 'studio') {
            // Studios can review instructors they've hired on their own listings
            $apps = JobApplication::with(['instructor:id,name,email,role', 'instructor.detail', 'jobListing:id,title,studio_id'])
                ->where('status', 'accepted')
                ->whereHas('jobListing', function ($q) use ($user) {
                    $q->where('studio_id', $user->id);
                })
                ->get();

            $alreadyReviewed = Review::where('reviewer_id', $user->id)
                ->pluck('job_listing_id', 'reviewee_id')
                ->map->toArray();

            $eligible = $apps->filter(function ($app) use ($user) {
                return !Review::where('reviewer_id', $user->id)
                    ->where('reviewee_id', $app->instructor_id)
                    ->where('job_listing_id', $app->job_listing_id)
                    ->exists();
            })->map(function ($app) {
                return [
                    'job_listing_id' => $app->job_listing_id,
                    'job_title'      => $app->jobListing?->title,
                    'counterparty'   => $app->instructor,
                ];
            })->values();

            return ApiResponse::success('Eligible reviews fetched', [
                'eligible' => $eligible,
            ]);
        }

        if ($user->role === 'instructor') {
            // Instructors can review studios whose listings accepted them
            $apps = JobApplication::with(['jobListing.studio:id,name,email,role', 'jobListing.studio.detail'])
                ->where('instructor_id', $user->id)
                ->where('status', 'accepted')
                ->get();

            $eligible = $apps->filter(function ($app) use ($user) {
                $studioId = $app->jobListing?->studio_id;
                if (!$studioId) return false;
                return !Review::where('reviewer_id', $user->id)
                    ->where('reviewee_id', $studioId)
                    ->where('job_listing_id', $app->job_listing_id)
                    ->exists();
            })->map(function ($app) {
                return [
                    'job_listing_id' => $app->job_listing_id,
                    'job_title'      => $app->jobListing?->title,
                    'counterparty'   => $app->jobListing?->studio,
                ];
            })->values();

            return ApiResponse::success('Eligible reviews fetched', [
                'eligible' => $eligible,
            ]);
        }

        return ApiResponse::success('Eligible reviews fetched', [
            'eligible' => [],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════

    /**
     * Check if reviewer has an accepted JobApplication connecting them
     * to reviewee. If a specific job_listing_id is provided, the check
     * is narrowed to that listing.
     */
    private function hasAcceptedRelationship(User $reviewer, User $reviewee, $jobListingId = null): bool
    {
        $query = JobApplication::where('status', 'accepted');

        if ($reviewer->role === 'studio' && $reviewee->role === 'instructor') {
            // Accepted application where this instructor was hired on one
            // of this studio's listings.
            $query->where('instructor_id', $reviewee->id)
                ->whereHas('jobListing', function ($q) use ($reviewer) {
                    $q->where('studio_id', $reviewer->id);
                });
        } elseif ($reviewer->role === 'instructor' && $reviewee->role === 'studio') {
            // Accepted application where this instructor was hired on one
            // of reviewee's listings.
            $query->where('instructor_id', $reviewer->id)
                ->whereHas('jobListing', function ($q) use ($reviewee) {
                    $q->where('studio_id', $reviewee->id);
                });
        } else {
            return false;
        }

        if ($jobListingId) {
            $query->where('job_listing_id', $jobListingId);
        }

        return $query->exists();
    }
}