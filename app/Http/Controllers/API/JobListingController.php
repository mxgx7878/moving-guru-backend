<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * JobListingController
 * ------------------------------------------------------------------
 *  PUBLIC / AUTHENTICATED
 *   GET    /jobs                 — browse active listings (instructors)
 *   GET    /jobs/{id}            — single listing detail
 *
 *  STUDIO-ONLY
 *   GET    /jobs/mine            — own listings (active + inactive)
 *   POST   /jobs                 — create listing
 *   PATCH  /jobs/{id}            — update own listing
 *   DELETE /jobs/{id}            — delete own listing
 *   GET    /jobs/{id}/applicants — list applicants for a listing
 *   PATCH  /applications/{id}/status — accept / reject an applicant
 *
 *  INSTRUCTOR-ONLY
 *   POST   /jobs/{id}/apply      — apply / express interest
 *   DELETE /applications/{id}    — withdraw own application
 *   GET    /applications/mine    — list own applications
 *
 * Re-apply lock
 * ------------------------------------------------------------------
 * When a studio rejects an applicant, that instructor is locked out of
 * re-applying to the same listing for 3 months. The lock is tracked via
 * the `rejected_at` column on job_applications. Helper methods below
 * compute the unlock date and decorate each application with a
 * `can_reapply_at` ISO string (or null if not rejected / lock expired).
 */
class JobListingController extends Controller
{
    /**
     * How long an instructor is locked out of a listing after rejection.
     * Centralised here so future changes need only one edit.
     */

      private function resolveOptionalUser(Request $request): ?User
    {
        // Happy path: route WAS behind auth:sanctum
        if ($user = $request->user()) {
            return $user;
        }
 
        // Fallback: manually parse the Bearer token
        $token = $request->bearerToken();
        if (!$token) return null;
 
        $pat = PersonalAccessToken::findToken($token);
        if (!$pat) return null;
 
        // Respect expiry if configured on the token
        if ($pat->expires_at && $pat->expires_at->isPast()) return null;
 
        return $pat->tokenable; // the User model
    }
    private const REAPPLY_LOCK_MONTHS = 3;

    /**
     * Compute the earliest moment an instructor can re-apply after a rejection.
     * Returns null if the app was never rejected or if the lock has expired.
     */
    private function computeReapplyUnlock(?JobApplication $app): ?\Carbon\Carbon
    {
        if (!$app || $app->status !== 'rejected' || !$app->rejected_at) {
            return null;
        }
        $unlock = $app->rejected_at->copy()->addMonths(self::REAPPLY_LOCK_MONTHS);
        return $unlock->isFuture() ? $unlock : null;
    }

    /**
     * Decorate an application with `can_reapply_at`. Mutates and returns.
     */
      private function decorateApplication(?JobApplication $app): ?JobApplication
    {
        if (!$app) return null;
        $unlock = $this->computeReapplyUnlock($app);
        $app->can_reapply_at = $unlock?->toIso8601String();
        return $app;
    }


    private function decorateCapacity(JobListing $job): JobListing
    {
        $job->positions_open = $job->positionsOpen();
        $job->is_full        = $job->isFull();
        return $job;
    }

    // ═══════════════════════════════════════════════════════════
    //  PUBLIC BROWSE
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/jobs
     * Active listings for instructors to browse.
     */
public function index(Request $request)
    {
        $user    = $this->resolveOptionalUser($request);
        $isAdmin = $user && $user->role === 'admin';

        $query = JobListing::with('studio')
            ->withCount(['applications as applicants_count' => function ($q) {
                $q->where('status', '!=', 'withdrawn');
            }])
            ->ofType($request->get('type'))
            ->inLocation($request->get('location'))
            ->hasDiscipline($request->get('discipline'));

             foreach (['country', 'city', 'suburb'] as $part) {
        if ($val = trim((string) $request->get($part, ''))) {
            $query->where('location', 'like', "%{$val}%");
        }
    }

        // Public + instructor view is scoped to active listings.
        // Admin sees everything and can filter explicitly.
        if (!$isAdmin) {
            $query->active();
        } elseif ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'full') {
                $query->whereRaw('positions_filled >= COALESCE(vacancies, 1)');
            }
        }

        // Free-text search — admin also searches across studio name/email
        if ($search = trim((string) $request->get('search', $request->get('q', '')))) {
            $query->where(function ($q) use ($search, $isAdmin) {
                $q->where('title',       'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location',    'like', "%{$search}%");

                if ($isAdmin) {
                    $q->orWhereHas('studio', function ($sq) use ($search) {
                        $sq->where('name',  'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                        // If users table has studio_name column, uncomment:
                        // ->orWhere('studio_name', 'like', "%{$search}%")
                    });
                }
            });
        }

        switch ($request->get('sort', 'recent')) {
        case 'name_asc':  $query->orderBy('title',      'asc');  break;
        case 'name_desc': $query->orderBy('title',      'desc'); break;
        case 'oldest':    $query->orderBy('created_at', 'asc');  break;
        case 'recent':
        default:          $query->orderBy('created_at', 'desc'); break;
        }

        // Instructor-only: attach own applications so Apply button state works.
        $myAppsByJob = collect();
        if ($user && $user->role === 'instructor') {
            $myAppsByJob = JobApplication::where('instructor_id', $user->id)
                ->get()
                ->keyBy('job_listing_id');
        }

        $perPage = min((int) $request->get('per_page', 20), 50);
        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function ($job) use ($myAppsByJob) {
            $this->decorateCapacity($job);
            $myApp = $myAppsByJob->get((int) $job->id);
            $job->application = $myApp ? $this->decorateApplication($myApp) : null;
            $job->has_applied = $myApp && $myApp->status !== 'withdrawn';
            return $job;
        });

        return ApiResponse::success('Jobs fetched', [
            'jobs' => $paginator->items(),
            'meta' => [
                'total'     => $paginator->total(),
                'page'      => $paginator->currentPage(),
                'per_page'  => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/jobs/{id}
     */
    public function show(Request $request, $id)
    {
        $job = JobListing::with('studio')
            ->withCount(['applications as applicants_count' => function ($q) {
                $q->where('status', '!=', 'withdrawn');
            }])
            ->findOrFail($id);

            $this->decorateCapacity($job);

        $user = $this->resolveOptionalUser($request);
        if ($user && $user->role === 'instructor') {
            $myApp = JobApplication::where('job_listing_id', $job->id)
                ->where('instructor_id', $user->id)
                ->first();
            $job->application = $myApp ? $this->decorateApplication($myApp) : null;
            $job->has_applied = $myApp && $myApp->status !== 'withdrawn';
        }

        return ApiResponse::success('Job fetched', ['job' => $job]);
    }

    // ═══════════════════════════════════════════════════════════
    //  STUDIO — MANAGE OWN LISTINGS
    // ═══════════════════════════════════════════════════════════

    public function mine(Request $request)
    {
        $jobs = JobListing::with('studio')
            ->withCount(['applications as applicants_count' => function ($q) {
                $q->where('status', '!=', 'withdrawn');
            }])
            ->where('studio_id', Auth::id())
            ->latest()
            ->get();

            $jobs->transform(fn ($j) => $this->decorateCapacity($j));

        return ApiResponse::success('Your listings fetched', [
            'jobs' => $jobs,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'               => 'required|string|max:255',
            'types'               => 'required_without:type|array|min:1',
            'types.*'             => 'in:hire,swap,energy_exchange',
            'type'                => 'sometimes|in:hire,swap,energy_exchange',
            'role_type'           => 'nullable|in:permanent,temporary,substitute,weekend_cover,casual',
            'description'         => 'required|string',
            'disciplines'         => 'nullable|array',
            'disciplines.*'       => 'string',
            'location'            => 'nullable|string|max:255',
            'start_date'          => 'nullable|date',
            'duration'            => 'nullable|string|max:100',
            'compensation'        => 'nullable|string|max:255',
            'requirements'        => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',  
            'qualification_level' => 'nullable|in:none,intermediate,diploma,bachelors,masters,doctorate,cert_200hr,cert_500hr,cert_comprehensive,cert_specialized',
            'is_active'           => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['studio_id']           = Auth::id();
        $data['role_type']           = $data['role_type'] ?? 'permanent';
        $data['qualification_level'] = $data['qualification_level'] ?? 'none';
        $data['is_active']           = $data['is_active'] ?? true;
        $incoming = $request->input('types');
        if (!is_array($incoming) || count($incoming) === 0) {
            $incoming = $request->filled('type') ? [$request->input('type')] : null;
        }
        if ($incoming) {
            $data['types'] = array_values(array_unique($incoming));
            $data['type']  = $data['types'][0];   // primary type for legacy display
        }

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('job_covers', 'public');
            $data['cover_image'] = rtrim(config('app.url'), '/') . '/storage/app/public/' . $path;
        }
        $job = JobListing::create($data);
        $job->loadMissing('studio');
        $job->applicants_count = 0;
        $this->decorateCapacity($job);

        return ApiResponse::success('Listing created successfully', [
            'job' => $job,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $job = JobListing::where('studio_id', Auth::id())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'               => 'sometimes|string|max:255',
            'types'               => 'required_without:type|array|min:1',
            'types.*'             => 'in:hire,swap,energy_exchange',
            'type'                => 'sometimes|in:hire,swap,energy_exchange',
            'role_type'           => 'sometimes|in:permanent,temporary,substitute,weekend_cover,casual',
            'description'         => 'sometimes|string',
            'disciplines'         => 'sometimes|array',
            'disciplines.*'       => 'string',
            'location'            => 'sometimes|nullable|string|max:255',
            'start_date'          => 'sometimes|nullable|date',
            'duration'            => 'sometimes|nullable|string|max:100',
            'compensation'        => 'sometimes|nullable|string|max:255',
            'requirements'        => 'sometimes|nullable|string',
            'qualification_level' => 'sometimes|in:none,intermediate,diploma,bachelors,masters,doctorate,cert_200hr,cert_500hr,cert_comprehensive,cert_specialized',
            'is_active'           => 'sometimes|boolean',
            'vacancies'           => 'sometimes|integer|min:1|max:999',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

         $data = $validator->validated();
 
        // Guard: you can't shrink vacancies below what's already been filled.
        if (isset($data['vacancies']) && $data['vacancies'] < $job->positions_filled) {
            return ApiResponse::error(
                "Vacancies can't be lower than positions already filled ({$job->positions_filled}).",
                [],
                422
            );
        }

        $incoming = $request->input('types');
        if (!is_array($incoming) || count($incoming) === 0) {
            $incoming = $request->filled('type') ? [$request->input('type')] : null;
        }
        if ($incoming) {
            $data['types'] = array_values(array_unique($incoming));
            $data['type']  = $data['types'][0];   // primary type for legacy display
        }

        if ($request->hasFile('cover_image')) {
    // Delete old file if it existed and was uploaded (not a remote URL)
        if ($job->cover_image && str_starts_with($job->cover_image, config('app.url'))) {
                $relative = str_replace(config('app.url') . '/storage/app/public/', '', $job->cover_image);
                Storage::disk('public')->delete($relative);
            }
            $path = $request->file('cover_image')->store('job_covers', 'public');
            $data['cover_image'] = rtrim(config('app.url'), '/') . '/storage/app/public/' . $path;
        }

        // Allow explicit removal via cover_image=null in the payload
        if ($request->exists('cover_image') && !$request->hasFile('cover_image') &&
            in_array($request->input('cover_image'), [null, '', 'null'], true)) {
            $data['cover_image'] = null;
        }
 
        $job->update($data);
        $job->loadMissing('studio');
        $job->applicants_count = $job->applications()
            ->where('status', '!=', 'withdrawn')->count();
        $this->decorateCapacity($job);
 
        return ApiResponse::success('Listing updated', ['job' => $job]);
    }

    public function destroy($id)
    {
        $job = JobListing::where('studio_id', Auth::id())->findOrFail($id);
        $job->delete();

        return ApiResponse::success('Listing deleted', ['id' => (int) $id]);
    }

    // ═══════════════════════════════════════════════════════════
    //  INSTRUCTOR — APPLY / WITHDRAW
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /api/jobs/{id}/apply
     * Reject lock: if the instructor was previously rejected on this
     * listing and the 3-month lock hasn't expired, returns 409 with
     * the unlock date so the UI can show it.
     */
    public function apply(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'instructor') {
            return ApiResponse::error('Only instructors can apply to listings.', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $job = JobListing::active()->findOrFail($id);

        if ($job->studio_id === $user->id) {
            return ApiResponse::error('You cannot apply to your own listing.', [], 422);
        }

        $existing = JobApplication::where('job_listing_id', $job->id)
            ->where('instructor_id', $user->id)
            ->first();

        // ── Reject lock ─────────────────────────────────────────
        if ($existing && $existing->status === 'rejected') {
            $unlock = $this->computeReapplyUnlock($existing);
            if ($unlock) {
                return ApiResponse::error(
                    "You were not selected for this listing. You can re-apply after " .
                    $unlock->format('M j, Y') . ".",
                    [
                        'status'         => 'rejected',
                        'rejected_at'    => $existing->rejected_at?->toIso8601String(),
                        'can_reapply_at' => $unlock->toIso8601String(),
                    ],
                    409
                );
            }
            // Lock expired — allow re-apply by reviving the row
            $existing->update([
                'status'      => 'pending',
                'message'     => $request->input('message') ?: $existing->message,
                'rejected_at' => null,
                'viewed_at'   => null,
            ]);
            $application = $this->decorateApplication($existing->fresh(['jobListing', 'instructor']));
            return ApiResponse::success('Application submitted', ['application' => $application], 201);
        }

        // Already applied and still active
        if ($existing && !in_array($existing->status, ['withdrawn'])) {
            return ApiResponse::error('You have already applied to this listing.', [], 409);
        }

        // Withdrawn previously → revive
        if ($existing && $existing->status === 'withdrawn') {
            $existing->update([
                'status'      => 'pending',
                'message'     => $request->input('message') ?: $existing->message,
                'rejected_at' => null,
            ]);
            $application = $this->decorateApplication($existing->fresh(['jobListing', 'instructor']));
            return ApiResponse::success('Application submitted', ['application' => $application], 201);
        }

        // Fresh application
        $application = JobApplication::create([
            'job_listing_id' => $job->id,
            'instructor_id'  => $user->id,
            'message'        => $request->input('message'),
            'status'         => 'pending',
        ])->fresh(['jobListing', 'instructor']);

        return ApiResponse::success('Application submitted', [
            'application' => $this->decorateApplication($application),
        ], 201);
    }

    /**
     * DELETE /api/applications/{id}
     * Instructor withdraws their own application.
     */
    public function withdraw(Request $request, $id)
    {
        $app = JobApplication::where('instructor_id', Auth::id())->findOrFail($id);

        $app->update(['status' => 'withdrawn']);

        return ApiResponse::success('Application withdrawn', ['id' => (int) $id]);
    }

    /**
     * GET /api/applications/mine
     * Instructor's own applications — decorated with can_reapply_at.
     */
    public function myApplications(Request $request)
    {
        $apps = JobApplication::with(['jobListing.studio'])
            ->where('instructor_id', Auth::id())
            ->latest()
            ->get()
            ->map(fn ($a) => $this->decorateApplication($a));

        return ApiResponse::success('Applications fetched', [
            'applications' => $apps,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  STUDIO — REVIEW APPLICANTS
    // ═══════════════════════════════════════════════════════════

 public function applicants(Request $request, $id)
    {
        $user = $request->user();
        $job  = JobListing::with('studio')->findOrFail($id);

        // Authorization — admin OR the owning studio, nobody else.
        $isOwner = $user->role === 'studio' && $job->studio_id === $user->id;
        $isAdmin = $user->role === 'admin';
        if (!$isOwner && !$isAdmin) {
            return ApiResponse::error('Forbidden.', [], 403);
        }

        $apps = JobApplication::with('instructor')
            ->where('job_listing_id', $job->id)
            ->where('status', '!=', 'withdrawn')
            ->latest()
            ->get();

        // Auto-mark pending → viewed ONLY when the studio owner is looking.
        // Admin moderation shouldn't mutate the studio's view state.
        if ($isOwner) {
            JobApplication::where('job_listing_id', $job->id)
                ->where('status', 'pending')
                ->update(['status' => 'viewed', 'viewed_at' => now()]);
        }

        $this->decorateCapacity($job);

        return ApiResponse::success('Applicants fetched', [
            'job'        => $job,
            'applicants' => $apps,
        ]);
    }

    /**
     * PATCH /api/applications/{id}/status
     * Studio accepts or rejects an applicant.
     * When status transitions to 'rejected', we stamp rejected_at so
     * the 3-month reapply lock can be enforced later. Transitioning
     * AWAY from rejected (e.g. mis-click → viewed) clears the stamp.
     */
    public function updateApplicationStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:viewed,accepted,rejected',
        ]);
 
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }
 
        $app = JobApplication::with('jobListing')->findOrFail($id);
 
        if (!$app->jobListing || $app->jobListing->studio_id !== Auth::id()) {
            return ApiResponse::error('Forbidden.', [], 403);
        }
 
        $newStatus = $request->status;
        $oldStatus = $app->status;
        $job       = $app->jobListing;
 
        // Short-circuit: no state change, no DB writes.
        if ($newStatus === $oldStatus) {
            return ApiResponse::success('No change', [
                'application' => $this->decorateApplication($app),
            ]);
        }
 
        // Guard: can't accept more applicants than remaining vacancies.
        if ($newStatus === 'accepted' && $oldStatus !== 'accepted') {
            if ($job->isFull()) {
                return ApiResponse::error(
                    'All positions have been filled for this listing.',
                    [],
                    409
                );
            }
        }
 
        DB::transaction(function () use (&$app, $job, $newStatus, $oldStatus) {
            // 1) Update the application row
            $update = ['status' => $newStatus];
            $update['viewed_at']   = $app->viewed_at ?? now();
            $update['rejected_at'] = $newStatus === 'rejected' ? now() : null;
            $app->update($update);
 
            // 2) Adjust the listing's filled counter based on the transition
            $wasAccepted = $oldStatus === 'accepted';
            $nowAccepted = $newStatus === 'accepted';
 
            if (!$wasAccepted && $nowAccepted) {
                $job->increment('positions_filled');
            } elseif ($wasAccepted && !$nowAccepted) {
                // Un-accepting (e.g. studio clicks decline after a mis-click).
                $job->decrement('positions_filled');
                // If the listing was auto-closed because it was full, reopen it
                // — otherwise respect whatever state the studio set manually.
                if (!$job->is_active && $job->positions_filled < $job->vacancies) {
                    $job->update(['is_active' => true]);
                }
            }
 
            // 3) Auto-close when full
            $job->refresh();
            if ($job->is_active && $job->isFull()) {
                $job->update(['is_active' => false]);
            }
        });
 
        $app->refresh()->loadMissing('instructor');
 
        return ApiResponse::success('Application updated', [
            'application' => $this->decorateApplication($app),
        ]);
    }


      public function adminActivate($id)
    {
        $job = JobListing::findOrFail($id);

        if ($job->isFull()) {
            return ApiResponse::error(
                'Cannot activate — all vacancies are already filled.',
                [], 422
            );
        }

        $job->update(['is_active' => true]);
        $job->loadMissing('studio');
        $this->decorateCapacity($job);

        return ApiResponse::success('Listing activated', ['job' => $job]);
    }

    /**
     * PATCH /api/admin/jobs/{id}/deactivate
     */
    public function adminDeactivate($id)
    {
        $job = JobListing::findOrFail($id);
        $job->update(['is_active' => false]);
        $job->loadMissing('studio');
        $this->decorateCapacity($job);

        return ApiResponse::success('Listing deactivated', ['job' => $job]);
    }

    /**
     * DELETE /api/admin/jobs/{id}
     */
    public function adminDestroy($id)
    {
        $job = JobListing::findOrFail($id);
        $job->delete();

        return ApiResponse::success('Listing deleted', ['id' => (int) $id]);
    }

}