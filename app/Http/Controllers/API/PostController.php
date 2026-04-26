<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * PostController
 * ------------------------------------------------------------------
 *  PUBLIC / AUTHENTICATED (instructor + studio)
 *   GET    /posts                    — announcements feed (role-scoped)
 *   GET    /posts/{id}               — single post
 *
 *  ADMIN-ONLY
 *   GET    /admin/posts              — all posts (any status)
 *   POST   /admin/posts              — create
 *   PUT    /admin/posts/{id}         — update
 *   DELETE /admin/posts/{id}         — delete
 *   PATCH  /admin/posts/{id}/publish
 *   PATCH  /admin/posts/{id}/unpublish
 */
class PostController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  PUBLIC / AUTHENTICATED
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/posts
     * Users (instructor/studio) see only PUBLISHED posts whose audience
     * matches their role (or 'all'). Pinned posts bubble to the top.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Post::with('author:id,name')
            ->published()
            ->forRole($user->role)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body',  'like', "%{$search}%");
            });
        }

        $perPage   = min((int) $request->get('per_page', 20), 50);
        $paginator = $query->paginate($perPage);

        return ApiResponse::success('Announcements fetched', [
            'posts' => $paginator->items(),
            'meta'  => [
                'total'     => $paginator->total(),
                'page'      => $paginator->currentPage(),
                'per_page'  => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/posts/{id}
     * User-facing single post — only published, only if audience matches.
     */
    public function show(Request $request, $id)
    {
        $user = Auth::user();

        $post = Post::with('author:id,name')
            ->published()
            ->forRole($user->role)
            ->findOrFail($id);

        return ApiResponse::success('Announcement fetched', ['post' => $post]);
    }

    // ═══════════════════════════════════════════════════════════
    //  ADMIN
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/admin/posts
     * Admin sees everything, can filter by type/audience/status/search.
     */
    public function adminIndex(Request $request)
    {
        $query = Post::with('author:id,name')
            ->orderByDesc('is_pinned')
            ->latest();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }
        if ($audience = $request->get('audience')) {
            $query->where('audience', $audience);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body',  'like', "%{$search}%");
            });
        }

        $perPage   = min((int) $request->get('per_page', 20), 50);
        $paginator = $query->paginate($perPage);

        return ApiResponse::success('Posts fetched', [
            'data' => $paginator->items(),
            'meta' => [
                'total'     => $paginator->total(),
                'page'      => $paginator->currentPage(),
                'per_page'  => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** Shared validation ruleset — used by both store() and update(). */
    private function postRules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';
        return [
            'type'           => "{$required}|in:announcement,event,news",
            'title'          => "{$required}|string|max:255",
            'body'           => "{$required}|string",
            'audience'       => "{$required}|in:all,instructors,studios",
            'cover_url'      => 'nullable|url|max:2048',
            'link_url'       => 'nullable|url|max:2048',
            'link_label'     => 'nullable|string|max:120',
            'event_date'     => 'nullable|date',
            'event_location' => 'nullable|string|max:255',
            'is_pinned'      => 'sometimes|boolean',
        ];
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->postRules(false));
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $data = $validator->validated();

        // Event posts must have a date.
        if ($data['type'] === 'event' && empty($data['event_date'])) {
            return ApiResponse::error(
                'Event date is required for event posts.', [], 422
            );
        }

        $post = Post::create([
            ...$data,
            'is_pinned'  => $data['is_pinned'] ?? false,
            'status'     => 'draft',
            'created_by' => Auth::id(),
        ]);

        $post->loadMissing('author:id,name');

        return ApiResponse::success('Post created.', ['data' => $post], 201);
    }

    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        $validator = Validator::make($request->all(), $this->postRules(true));
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $data = $validator->validated();

        // If switching to event type, enforce date requirement.
        $type = $data['type'] ?? $post->type;
        $eventDate = $data['event_date'] ?? $post->event_date;
        if ($type === 'event' && empty($eventDate)) {
            return ApiResponse::error(
                'Event date is required for event posts.', [], 422
            );
        }

        $post->update($data);
        $post->loadMissing('author:id,name');

        return ApiResponse::success('Post updated.', ['data' => $post]);
    }

    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return ApiResponse::success('Post deleted.', ['id' => (int) $id]);
    }

    public function publish($id)
    {
        $post = Post::findOrFail($id);
        $post->update([
            'status'       => 'published',
            'published_at' => $post->published_at ?? now(),
        ]);
        $post->loadMissing('author:id,name');

        return ApiResponse::success('Post published.', ['data' => $post]);
    }

    public function unpublish($id)
    {
        $post = Post::findOrFail($id);
        $post->update(['status' => 'draft']);
        $post->loadMissing('author:id,name');

        return ApiResponse::success('Post unpublished.', ['data' => $post]);
    }
}