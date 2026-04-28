<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Artisan;

/**
 * UserManagementController
 * ------------------------------------------------------------------
 * Admin-only CRUD + lifecycle management for instructors, studios and
 * other admins. Mounted under prefix('admin') with is.admin middleware.
 *
 *   GET    /admin/users                 list (role, status, q, per_page)
 *   POST   /admin/users                 create
 *   GET    /admin/users/{id}            detail with stats
 *   PATCH  /admin/users/{id}            update (profile fields)
 *   PATCH  /admin/users/{id}/approve    status → active
 *   PATCH  /admin/users/{id}/reject     status → rejected (+ reason)
 *   PATCH  /admin/users/{id}/suspend    status → suspended (+ reason)
 *   PATCH  /admin/users/{id}/activate   status → active
 *   PATCH  /admin/users/{id}/verify     toggle is_verified
 *   DELETE /admin/users/{id}            permanent delete
 */
class UserManagementController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  LIST
    // ═══════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = User::with('detail')->whereIn('role', ['instructor', 'studio']);

        // Filters
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhereHas('detail', function ($d) use ($q) {
                        $d->where('studioName', 'like', "%{$q}%")
                          ->orWhere('location', 'like', "%{$q}%");
                    });
            });
        }

        if ($activity = $request->query('activity')) {
            $threshold = match ($activity) {
                'stale_3m'  => now()->subDays(90),
                'stale_30d' => now()->subDays(30),
                'recent'    => now()->subDays(7),
                default     => null,
            };

           if ($threshold && in_array($activity, ['stale_3m', 'stale_30d'])) {
                $query->where(function ($q) use ($threshold) {
                    $q->where('last_login_at', '<', $threshold)
                    ->orWhereNull('last_login_at');
                })->whereHas('detail', function ($q) {
                    $q->where('profileStatus', 'active');
                });
            } elseif ($threshold && $activity === 'recent') {
                $query->where('last_login_at', '>=', $threshold);
            }
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $paginator = $query->latest('created_at')->paginate($perPage);

        $items = $paginator->getCollection()
            ->map(fn ($u) => $this->transformUser($u, withStats: false))
            ->values();

        return response()->json([
            'status'      => true,
            'status_code' => 200,
            'message'     => 'Users fetched',
            'data'        => $items,
            'meta'        => [
                'total'     => $paginator->total(),
                'page'      => $paginator->currentPage(),
                'per_page'  => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  SHOW — detail with stats
    // ═══════════════════════════════════════════════════════════
    public function show($id)
    {
        $user = User::with('detail')->find($id);
        if (!$user) return ApiResponse::error('User not found', [], 404);

        return ApiResponse::success('User fetched', [
            'user' => $this->transformUser($user, withStats: true),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  CREATE — admin manually onboards a user
    // ═══════════════════════════════════════════════════════════
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role'        => 'required|string|in:instructor,studio,admin',
            'name'        => 'required_without:studio_name|string|max:255',
            'studio_name' => 'required_if:role,studio|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|string|min:6',
            'phone'       => 'nullable|string|max:40',
            'location'    => 'nullable|string|max:255',
            'bio'         => 'nullable|string|max:2000',
            'status'      => 'nullable|string|in:active,pending,suspended',
            'is_verified' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $data = $validator->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'        => $data['role'] === 'studio'
                                    ? ($data['studio_name'] ?? $data['name'] ?? 'Studio')
                                    : $data['name'],
                'email'       => $data['email'],
                'password'    => Hash::make($data['password']),
                'role'        => $data['role'],
                'status'      => $data['status']      ?? 'active',
                'is_verified' => $data['is_verified'] ?? false,
                'approved_at' => ($data['status'] ?? 'active') === 'active' ? now() : null,
                'approved_by' => ($data['status'] ?? 'active') === 'active' ? Auth::id() : null,
            ]);

            // Always create a matching detail row so the profile page works
            $user->detail()->create([
                'bio'           => $data['bio']      ?? null,
                'location'      => $data['location'] ?? null,
                'phone'         => $data['phone']    ?? null,
                'studioName'    => $data['role'] === 'studio' ? ($data['studio_name'] ?? null) : null,
                'profileStatus' => 'active',
                'plan'          => 'monthly',
            ]);

            return $user->fresh('detail');
        });

        return ApiResponse::success('User created', [
            'user' => $this->transformUser($user, withStats: true),
        ], 201);
    }

    // ═══════════════════════════════════════════════════════════
    //  UPDATE — admin edits user
    // ═══════════════════════════════════════════════════════════
    public function update(Request $request, $id)
    {
        $user = User::with('detail')->find($id);
        if (!$user) return ApiResponse::error('User not found', [], 404);

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:255',
            'studio_name' => 'sometimes|nullable|string|max:255',
            'email'       => 'sometimes|email|unique:users,email,' . $user->id,
            'phone'       => 'sometimes|nullable|string|max:40',
            'location'    => 'sometimes|nullable|string|max:255',
            'bio'         => 'sometimes|nullable|string|max:2000',
            'status'      => 'sometimes|string|in:active,pending,suspended,rejected',
            'is_verified' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $data = $validator->validated();

        DB::transaction(function () use ($user, $data) {
            // User-level fields
            $userFields = array_intersect_key($data, array_flip(['name', 'email', 'status', 'is_verified']));
            if (!empty($userFields)) $user->update($userFields);

            // Detail-level fields
            $detail = $user->detail ?: $user->detail()->create([]);
            $detailFields = [];
            if (array_key_exists('bio',         $data)) $detailFields['bio']        = $data['bio'];
            if (array_key_exists('phone',       $data)) $detailFields['phone']      = $data['phone'];
            if (array_key_exists('location',    $data)) $detailFields['location']   = $data['location'];
            if (array_key_exists('studio_name', $data)) $detailFields['studioName'] = $data['studio_name'];
            if (!empty($detailFields)) $detail->update($detailFields);
        });

        return ApiResponse::success('User updated', [
            'user' => $this->transformUser($user->fresh('detail'), withStats: true),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  LIFECYCLE ACTIONS
    // ═══════════════════════════════════════════════════════════

    public function approve($id)
    {
        $user = User::find($id);
        if (!$user) return ApiResponse::error('User not found', [], 404);

        if ($user->status === 'active') {
            return ApiResponse::error('User is already active.', [], 422);
        }

        $user->update([
            'status'           => 'active',
            'approved_at'      => now(),
            'approved_by'      => Auth::id(),
            'rejected_at'      => null,
            'rejection_reason' => null,
        ]);

        return ApiResponse::success('User approved', [
            'user' => $this->transformUser($user->fresh('detail')),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) return ApiResponse::error('User not found', [], 404);

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $user->update([
            'status'           => 'rejected',
            'rejected_at'      => now(),
            'rejection_reason' => $request->input('reason'),
        ]);

        // Revoke any issued tokens so they can't still use the app
        $user->tokens()->delete();

        return ApiResponse::success('Registration rejected', [
            'user' => $this->transformUser($user->fresh('detail')),
        ]);
    }

    public function suspend(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) return ApiResponse::error('User not found', [], 404);

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $user->update([
            'status'            => 'suspended',
            'suspended_at'      => now(),
            'suspension_reason' => $request->input('reason'),
        ]);

        $user->tokens()->delete();

        return ApiResponse::success('User suspended', [
            'user' => $this->transformUser($user->fresh('detail')),
        ]);
    }

    public function activate($id)
    {
        $user = User::find($id);
        if (!$user) return ApiResponse::error('User not found', [], 404);

        $user->update([
            'status'            => 'active',
            'suspended_at'      => null,
            'suspension_reason' => null,
            'rejected_at'       => null,
            'rejection_reason'  => null,
        ]);

        return ApiResponse::success('User activated', [
            'user' => $this->transformUser($user->fresh('detail')),
        ]);
    }

    public function verify(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) return ApiResponse::error('User not found', [], 404);

        if ($user->role !== 'studio') {
            return ApiResponse::error('Only studio accounts can be verified.', [], 422);
        }

        $isVerified = $request->has('is_verified')
            ? (bool) $request->input('is_verified')
            : !$user->is_verified;

        $user->update(['is_verified' => $isVerified]);

        return ApiResponse::success(
            $isVerified ? 'Studio marked as verified' : 'Verification removed',
            ['user' => $this->transformUser($user->fresh('detail'))],
        );
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) return ApiResponse::error('User not found', [], 404);

        if ($user->id === Auth::id()) {
            return ApiResponse::error('You cannot delete your own admin account.', [], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success('User deleted', ['id' => (int) $id]);
    }

    // ═══════════════════════════════════════════════════════════
    //  TRANSFORM — flatten user+detail into the shape the admin UI expects
    // ═══════════════════════════════════════════════════════════
    private function transformUser(User $user, bool $withStats = false): array
    {
        $d = $user->detail;

        $out = [
            'id'                 => $user->id,
            'role'               => $user->role,
            'name'               => $user->name,
            'studio_name'        => $d?->studioName,
            'email'              => $user->email,
            'phone'              => $d?->phone,
            'location'           => $d?->location ?? null,
            'bio'                => $d?->bio,
            'disciplines'        => $d?->disciplines ?? [],
            'profile_picture'    => $d?->profile_picture,
            'background_image'   => $d?->background_image,

            // Lifecycle
            'status'             => $user->status,
            'is_active'          => $user->status === 'active',
            'is_verified'        => (bool) $user->is_verified,

            // Timestamps
            'approved_at'        => $user->approved_at?->toIso8601String(),
            'suspended_at'       => $user->suspended_at?->toIso8601String(),
            'suspension_reason'  => $user->suspension_reason,
            'rejected_at'        => $user->rejected_at?->toIso8601String(),
            'rejection_reason'   => $user->rejection_reason,
            'last_login_at'      => $user->last_login_at?->toIso8601String(),
            'created_at'         => $user->created_at?->toIso8601String(),
            'updated_at'         => $user->updated_at?->toIso8601String(),
        ];

        if ($withStats) {
            $out['stats'] = $this->computeStats($user);
        }

        return $out;
    }

    private function computeStats(User $user): array
    {
        $stats = [
            'applications_count' => 0,
            'saved_by_count'     => 0,
            'grow_posts_count'   => 0,
            'jobs_count'         => 0,
        ];

        // Grow posts — shared between roles
        if (Schema::hasTable('grow_posts')) {
            $stats['grow_posts_count'] = DB::table('grow_posts')
                ->where('user_id', $user->id)->count();
        }

        if ($user->role === 'instructor') {
            if (Schema::hasTable('job_applications')) {
                $stats['applications_count'] = DB::table('job_applications')
                    ->where('instructor_id', $user->id)
                    ->where('status', '!=', 'withdrawn')
                    ->count();
            }
            if (Schema::hasTable('saved_instructors')) {
                $stats['saved_by_count'] = DB::table('saved_instructors')
                    ->where('instructor_id', $user->id)->count();
            }
        }

        if ($user->role === 'studio') {
            if (Schema::hasTable('job_listings')) {
                $stats['jobs_count'] = DB::table('job_listings')
                    ->where('studio_id', $user->id)->count();
            }
        }

        return $stats;
    }

    public function runStaleSweep(Request $request)
    {
        $exitCode = Artisan::call('users:auto-deactivate-stale');
        $output   = Artisan::output();

        return ApiResponse::success('Stale-user sweep complete', [
            'exit_code' => $exitCode,
            'output'    => $output,
        ]);
    }
}