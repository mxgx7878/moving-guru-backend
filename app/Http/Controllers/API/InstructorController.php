<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProfileView;
use App\Models\SavedInstructor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * InstructorController
 * ------------------------------------------------------------------
 *  AUTHENTICATED — any role
 *   GET  /instructors                   — list active instructors (filterable)
 *   GET  /instructors/{id}              — single instructor + record profile view
 *
 *  STUDIO-ONLY (guarded by is.studio middleware in routes)
 *   GET  /instructors/saved             — studio's saved instructors (full profiles)
 *   POST /instructors/save              — body { instructor_id }
 *   POST /instructors/unsave            — body { instructor_id }
 */
class InstructorController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  BROWSE
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/instructors
     *
     * By default returns only instructors who have toggled their
     * profile to "Actively Seeking" (profileStatus = 'active') per
     * the client spec. Pass ?active_only=false to include inactive.
     *
     * Filters (all optional):
     *   ?discipline=Reformer%20Pilates
     *   ?openTo=Direct%20Hire
     *   ?location=Bali                (matches location, countryFrom, travelingTo)
     *   ?search=keyword               (name, bio)
     *   ?per_page=20
     */
    public function index(Request $request)
    {
        $activeOnly = $request->boolean('active_only', true);

        $query = User::where('role', 'instructor')
            ->with('detail')
            ->when(isset($request->isDeleted), function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('isDeleted')->orWhere('isDeleted', false);
                });
            });

        // Join user_details for filtering on detail columns
        $query->whereHas('detail', function ($q) use ($request, $activeOnly) {
            if ($activeOnly) {
                $q->where('profileStatus', 'active');
            }

            if ($discipline = $request->get('discipline')) {
                $q->whereJsonContains('disciplines', $discipline);
            }

            if ($openTo = $request->get('openTo')) {
                $q->whereJsonContains('openTo', $openTo);
            }

            if ($location = trim((string) $request->get('location', ''))) {
                $q->where(function ($sub) use ($location) {
                    $sub->where('location',    'like', "%{$location}%")
                        ->orWhere('countryFrom', 'like', "%{$location}%")
                        ->orWhere('travelingTo', 'like', "%{$location}%");
                });
            }

            if ($bio = $request->get('search')) {
                // Search inside detail.bio; name search is handled on the
                // outer query so we don't have to cross-scope.
                $q->orWhere('bio', 'like', "%{$bio}%");
            }
        });

        // Outer name search — kept separate from detail filters so the
        // name-OR-bio search works even when name lives on the users table.
        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // If caller is a studio, attach `is_saved` so the UI can render
        // the heart icon state without a separate lookup.
        $user = $request->user();
        $savedIds = [];
        if ($user && $user->role === 'studio') {
            $savedIds = SavedInstructor::where('studio_id', $user->id)
                ->pluck('instructor_id')
                ->toArray();
        }

        $perPage = min((int) $request->get('per_page', 20), 50);
        $paginator = $query->latest('users.created_at')->paginate($perPage);

        $paginator->getCollection()->transform(function ($inst) use ($savedIds) {
            $inst->is_saved = in_array($inst->id, $savedIds);
            return $inst;
        });

        return ApiResponse::success('Instructors fetched', [
            'instructors' => $paginator->items(),
            'meta' => [
                'total'     => $paginator->total(),
                'page'      => $paginator->currentPage(),
                'per_page'  => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/instructors/{id}
     *
     * Single instructor detail. Records a profile view if the viewer
     * is not the instructor themselves (so dashboards can show "who
     * looked at my profile this week").
     */
    public function show(Request $request, $id)
    {
        $instructor = User::where('role', 'instructor')
            ->with('detail')
            ->findOrFail($id);

        $viewer = $request->user();

        // Record profile view — skip when self-viewing.
        if ($viewer && $viewer->id !== $instructor->id) {
            // Wrap in try so a transient DB hiccup doesn't break the fetch.
            try {
                ProfileView::create([
                    'viewer_id'      => $viewer->id,
                    'viewed_user_id' => $instructor->id,
                    'viewed_at'      => now(),
                ]);
            } catch (\Throwable $e) {
                // Swallow — view tracking is best-effort.
            }
        }

        // Attach is_saved for studio viewers
        if ($viewer && $viewer->role === 'studio') {
            $instructor->is_saved = SavedInstructor::where('studio_id', $viewer->id)
                ->where('instructor_id', $instructor->id)
                ->exists();
        }

        return ApiResponse::success('Instructor fetched', [
            'instructor' => $instructor,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  FAVOURITES (studio-only — gated in routes/api.php)
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/instructors/saved
     *
     * Returns the studio's saved instructors as full profile objects
     * (not just IDs), so the Favourites page can render cards without
     * needing the browse list loaded first.
     */
    public function saved(Request $request)
    {
        $studioId = Auth::id();

        $saved = User::where('role', 'instructor')
            ->with('detail')
            ->join('saved_instructors as si', 'si.instructor_id', '=', 'users.id')
            ->where('si.studio_id', $studioId)
            ->orderByDesc('si.created_at')
            ->select('users.*', 'si.created_at as saved_at')
            ->get();

        // Every returned profile is, by definition, saved by this studio.
        $saved->transform(function ($inst) {
            $inst->is_saved = true;
            return $inst;
        });

        return ApiResponse::success('Saved instructors fetched', [
            'instructors' => $saved,
        ]);
    }

    /**
     * POST /api/instructors/save
     * body: { instructor_id }
     */
    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instructor_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $studioId = Auth::id();
        $instructorId = (int) $request->input('instructor_id');

        // Verify the target is an instructor — prevents studios saving
        // other studios (or admins) via the same endpoint.
        $isInstructor = User::where('id', $instructorId)
            ->where('role', 'instructor')
            ->exists();

        if (!$isInstructor) {
            return ApiResponse::error('Target user is not an instructor.', [], 422);
        }

        if ($studioId === $instructorId) {
            // Edge case: a user who is somehow both roles can't save themselves.
            return ApiResponse::error('You cannot save yourself.', [], 422);
        }

        // Idempotent: firstOrCreate instead of create to swallow duplicate posts.
        SavedInstructor::firstOrCreate([
            'studio_id'     => $studioId,
            'instructor_id' => $instructorId,
        ]);

        return ApiResponse::success('Instructor saved', [
            'instructor_id' => $instructorId,
        ]);
    }

    /**
     * POST /api/instructors/unsave
     * body: { instructor_id }
     */
    public function unsave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instructor_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $studioId = Auth::id();
        $instructorId = (int) $request->input('instructor_id');

        SavedInstructor::where('studio_id', $studioId)
            ->where('instructor_id', $instructorId)
            ->delete();

        return ApiResponse::success('Instructor unsaved', [
            'instructor_id' => $instructorId,
        ]);
    }
}