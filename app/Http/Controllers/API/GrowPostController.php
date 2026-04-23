<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GrowPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GrowPostController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  PUBLIC — No auth required
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/grow-posts
     * List all approved, active posts with optional filters.
     * Used by public website AND portal browse views.
     */
    public function index(Request $request)
    {
        $query = GrowPost::with('user')
            ->approved()
            ->active()
            ->featuredFirst();

        // Filter by type: training | retreat | event
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by location (partial match)
        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        // Filter by discipline (JSON column contains value)
        if ($request->filled('discipline')) {
            $query->whereJsonContains('disciplines', $request->discipline);
        }

        // Keyword search across title, description, location
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sub) use ($q) {
                $sub->where('title',       'like', "%{$q}%")
                    ->orWhere('description','like', "%{$q}%")
                    ->orWhere('location',   'like', "%{$q}%");
            });
        }

        // Date filters
        if ($request->filled('date_from')) {
            $query->where('date_to', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('date_from', '<=', $request->date_to);
        }

        // Featured only (for homepage spotlight)
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $perPage = min((int) $request->get('per_page', 12), 50);
        $posts   = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $posts->items(),
            'meta'    => [
                'total'        => $posts->total(),
                'page'         => $posts->currentPage(),
                'per_page'     => $posts->perPage(),
                'last_page'    => $posts->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/grow-posts/{id}
     * Single post detail — public.
     */
    public function show($id)
    {
        $post = GrowPost::with('user')->approved()->active()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $post,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  AUTHENTICATED — Logged-in users only
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/grow-posts/my
     * Get own posts (studio or instructor).
     */
    public function myPosts()
    {
        $posts = GrowPost::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $posts,
        ]);
    }

    /**
     * POST /api/grow-posts
     * Create a new grow post (requires subscription).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'         => 'required|in:training,retreat,event',
            'title'        => 'required|string|max:255',
            'subtitle'     => 'nullable|string|max:255',
            'description'  => 'required|string',
            'location'     => 'required|string|max:255',
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date|after_or_equal:date_from',
            'price'        => 'nullable|string|max:100',
            'spots'        => 'nullable|integer|min:1',
            'spots_left'   => 'nullable|integer|min:0',
            'disciplines'  => 'nullable|array',
            'disciplines.*'=> 'string',
            'tags'         => 'nullable|array',
            'tags.*'       => 'string',
            'external_url' => 'nullable|url',
            'color'        => 'nullable|string|max:20',
           'cover_image'  => 'nullable|file|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'expiry_date'  => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Handle image uploads (base64 → store file)
      $imageUrls = [];
if ($request->hasFile('cover_image') && $request->file('cover_image')->isValid()) {
    $path = $request->file('cover_image')->store('grow_posts', 'public');
    $imageUrls[] = Storage::url($path);
}

        $post = GrowPost::create([
            'user_id'      => Auth::id(),
            'type'         => $request->type,
            'title'        => $request->title,
            'subtitle'     => $request->subtitle,
            'description'  => $request->description,
            'location'     => $request->location,
            'date_from'    => $request->date_from,
            'date_to'      => $request->date_to,
            'price'        => $request->price,
            'spots'        => $request->spots,
            'spots_left'   => $request->spots_left ?? $request->spots,
            'disciplines'  => $request->disciplines ?? [],
            'tags'         => $request->tags ?? [],
            'images'       => $imageUrls,
            'external_url' => $request->external_url,
            'color'        => $request->color,
            'status'       => 'pending',   // Admin must approve before it's public
            'expires_at'   => $request->expiry_date,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Post submitted successfully. It will be visible once approved.',
            'data'    => $post->load('user'),
        ], 201);
    }

    /**
     * PUT /api/grow-posts/{id}
     * Update own post (owner only).
     */
    public function update(Request $request, $id)
    {
        $post = GrowPost::where('user_id', Auth::id())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'        => 'sometimes|string|max:255',
            'subtitle'     => 'nullable|string|max:255',
            'description'  => 'sometimes|string',
            'location'     => 'sometimes|string|max:255',
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date',
            'price'        => 'nullable|string|max:100',
            'spots'        => 'nullable|integer|min:1',
            'spots_left'   => 'nullable|integer|min:0',
            'disciplines'  => 'nullable|array',
            'tags'         => 'nullable|array',
            'external_url' => 'nullable|url',
            'expiry_date'  => 'nullable|date',
    'cover_image'  => 'nullable|file|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

$newImages = null; // null = don't touch
if ($request->hasFile('cover_image') && $request->file('cover_image')->isValid()) {
    // Delete the previous file from disk if we can figure out its path.
    $existing = $post->images[0] ?? null;
    if ($existing) {
        // Strip the /storage/ prefix to get the actual file path on the disk.
        $oldRelative = ltrim(str_replace('/storage/', '', parse_url($existing, PHP_URL_PATH) ?? ''), '/');
        if ($oldRelative && Storage::disk('public')->exists($oldRelative)) {
            Storage::disk('public')->delete($oldRelative);
        }
    }
    $path = $request->file('cover_image')->store('grow_posts', 'public');
    $newImages = [Storage::url($path)];
} elseif ($request->boolean('remove_cover_image')) {
    // Explicit wipe
    $existing = $post->images[0] ?? null;
    if ($existing) {
        $oldRelative = ltrim(str_replace('/storage/', '', parse_url($existing, PHP_URL_PATH) ?? ''), '/');
        if ($oldRelative && Storage::disk('public')->exists($oldRelative)) {
            Storage::disk('public')->delete($oldRelative);
        }
    }
    $newImages = [];
}

        $post->update(array_filter([
            'title'        => $request->title,
            'subtitle'     => $request->subtitle,
            'description'  => $request->description,
            'location'     => $request->location,
            'date_from'    => $request->date_from,
            'date_to'      => $request->date_to,
            'price'        => $request->price,
            'spots'        => $request->spots,
            'spots_left'   => $request->spots_left,
            'disciplines'  => $request->disciplines,
            'tags'         => $request->tags,
            'external_url' => $request->external_url,
            'expires_at'   => $request->expiry_date,
            'images'       => $newImages, // only update if new images provided
        ], fn($v) => $v !== null));


        // Re-submit for approval if content changed significantly
       $post->update(['status' => 'pending', 'rejection_reason' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully.',
            'data'    => $post->fresh('user'),
        ]);
    }

    /**
     * DELETE /api/grow-posts/{id}
     * Delete own post.
     */
    public function destroy($id)
    {
        $post = GrowPost::where('user_id', Auth::id())->findOrFail($id);
        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully.',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  ADMIN — Requires admin role middleware
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/admin/grow-posts
     * List all posts (any status) for admin moderation.
     */
    public function adminIndex(Request $request)
    {
        $query = GrowPost::with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $posts = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $posts->items(),
            'meta'    => ['total' => $posts->total()],
        ]);
    }

    /**
     * PATCH /api/admin/grow-posts/{id}/approve
     */
    public function approve($id)
    {
        $post = GrowPost::findOrFail($id);
        $post->update(['status' => 'approved', 'rejection_reason' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Post approved and is now live.',
            'data'    => $post,
        ]);
    }

    /**
     * PATCH /api/admin/grow-posts/{id}/reject
     */
    public function reject(Request $request, $id)
    {
        $post = GrowPost::findOrFail($id);
        $post->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason ?? null,
        ]);
        return response()->json(['success' => true, 'message' => 'Post rejected.', 'data' => $post->fresh('user')]);
    }

    /**
     * PATCH /api/admin/grow-posts/{id}/boost
     * Toggle featured placement.
     */
    public function boost(Request $request, $id)
    {
        $post = GrowPost::findOrFail($id);
        $post->update([
            'is_featured' => !$post->is_featured,
            'boost_until' => $request->boost_until ?? now()->addDays(7),
        ]);

        return response()->json([
            'success' => true,
            'message' => $post->is_featured ? 'Post boosted.' : 'Boost removed.',
            'data'    => $post,
        ]);
    }
    public function adminDestroy($id)
{
    $post = GrowPost::findOrFail($id);
    $post->delete();

    return response()->json([
        'success' => true,
        'message' => 'Post deleted by admin.',
    ]);
}
}