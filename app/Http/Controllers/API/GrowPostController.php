<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GrowPost;
use App\Models\GrowPostPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * Create a paid Grow post for an authenticated user.
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
            'spots'        => 'nullable|required_with:spots_left|integer|min:1',
            'spots_left'   => 'nullable|integer|min:0|lte:spots',
            'disciplines'  => 'nullable|array',
            'disciplines.*'=> 'string',
            'tags'         => 'nullable|array',
            'tags.*'       => 'string',
            'external_url' => 'nullable|url',
            'color'        => 'nullable|string|max:20',
            'cover_image'  => 'nullable|file|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'payment_intent_id' => 'required|string|starts_with:pi_',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // 🔥 Same as profile logic
        $generateUrl = fn($path) => config('app.url') . '/storage/app/public/' . $path;

        $imageUrls = [];

        if ($request->hasFile('cover_image') && $request->file('cover_image')->isValid()) {
            $path = $request->file('cover_image')->store('grow_posts', 'public');
            $imageUrls[] = $generateUrl($path);
        }

        $post = DB::transaction(function () use ($request, $imageUrls) {
            $payment = GrowPostPayment::where('user_id', Auth::id())
                ->where('purpose', 'listing')
                ->where('status', 'succeeded')
                ->whereNull('grow_post_id')
                ->where('stripe_payment_intent_id', $request->payment_intent_id)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                abort(422, 'A successful Grow listing payment is required.');
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
                'status'       => 'pending',
                // Payment is taken now; the purchased live period starts on approval.
                'expires_at'   => null,
            ]);

            $payment->update(['grow_post_id' => $post->id]);
            return $post;
        });

        return response()->json([
            'success' => true,
            'message' => 'Post submitted successfully. It will be visible once approved.',
            'data'    => $post->load('user'),
        ], 201);
    }

    /** POST /api/public/grow-posts — paid public submission. */
    public function publicStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact_name' => 'required|string|max:120',
            'contact_email' => 'required|email|max:255',
            'organization_name' => 'required|string|max:160',
            'type' => 'required|in:training,retreat,event',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'date_label' => 'nullable|string|max:120',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'price' => 'nullable|string|max:100',
            'spots' => 'nullable|required_with:spots_left|integer|min:1',
            'spots_left' => 'nullable|integer|min:0|lte:spots',
            'disciplines' => 'nullable|array',
            'tags' => 'nullable|array',
            'external_url' => 'nullable|url',
            'cover_image' => 'nullable|file|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'payment_intent_id' => 'required|string|starts_with:pi_',
            'submission_token' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $payment = GrowPostPayment::whereNull('user_id')
            ->whereNull('grow_post_id')
            ->where('purpose', 'listing')
            ->where('status', 'succeeded')
            ->where('contact_email', strtolower($request->contact_email))
            ->where('stripe_payment_intent_id', $request->payment_intent_id)
            ->where('public_token_hash', hash('sha256', $request->submission_token))
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'A successful Grow listing payment is required.'], 422);
        }

        $images = [];
        if ($request->hasFile('cover_image') && $request->file('cover_image')->isValid()) {
            $path = $request->file('cover_image')->store('grow_posts', 'public');
            $images[] = config('app.url') . '/storage/app/public/' . $path;
        }

        $post = DB::transaction(function () use ($request, $payment, $images) {
            $lockedPayment = GrowPostPayment::whereKey($payment->id)
                ->whereNull('grow_post_id')->lockForUpdate()->firstOrFail();
            $post = GrowPost::create([
                'user_id' => null,
                'contact_name' => $request->contact_name,
                'contact_email' => strtolower($request->contact_email),
                'organization_name' => $request->organization_name,
                'type' => $request->type,
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'description' => $request->description,
                'location' => $request->location,
                'date_label' => $request->date_label,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'price' => $request->price,
                'spots' => $request->spots,
                'spots_left' => $request->spots_left ?? $request->spots,
                'disciplines' => $request->disciplines ?? [],
                'tags' => $request->tags ?? [],
                'images' => $images,
                'external_url' => $request->external_url,
                'status' => 'pending',
                'expires_at' => null,
            ]);
            $lockedPayment->update(['grow_post_id' => $post->id]);
            return $post;
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment received. Your Grow post is pending approval.',
            'data' => $post,
        ], 201);
    }

    /**
     * PUT /api/grow-posts/{id}
     * Update own post (owner only).
     */
    public function update(Request $request, $id)
    {
        $post = GrowPost::where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'rejected'])
            ->findOrFail($id);

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
            'cover_image'  => 'nullable|file|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        $validator->after(function ($validator) use ($request, $post) {
            $spots = $request->has('spots') ? $request->spots : $post->spots;
            $left = $request->has('spots_left') ? $request->spots_left : $post->spots_left;
            if ($spots !== null && $left !== null && (int) $left > (int) $spots) {
                $validator->errors()->add('spots_left', 'Spots left cannot be greater than total spots.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        // 🔥 Same URL logic
        $generateUrl = fn($path) => config('app.url') . '/storage/app/public/' . $path;

        $newImages = null;

        if ($request->hasFile('cover_image') && $request->file('cover_image')->isValid()) {

            // delete old image
            $existing = $post->images[0] ?? null;

            if ($existing) {
                $oldRelative = ltrim(
                    str_replace('/storage/app/public/', '', parse_url($existing, PHP_URL_PATH) ?? ''),
                    '/'
                );

                if ($oldRelative && Storage::disk('public')->exists($oldRelative)) {
                    Storage::disk('public')->delete($oldRelative);
                }
            }

            $path = $request->file('cover_image')->store('grow_posts', 'public');
            $newImages = [$generateUrl($path)];

        } elseif ($request->boolean('remove_cover_image')) {

            $existing = $post->images[0] ?? null;

            if ($existing) {
                $oldRelative = ltrim(
                    str_replace('/storage/app/public/', '', parse_url($existing, PHP_URL_PATH) ?? ''),
                    '/'
                );

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
            'images'       => $newImages,
        ], fn($v) => $v !== null));

        // optional: always resubmit
        $post->update([
            'status' => 'pending',
            'rejection_reason' => null
        ]);

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
        $post = GrowPost::with('growPayments')->where('status', 'pending')->findOrFail($id);
        $payment = $post->growPayments
            ->where('purpose', 'listing')
            ->where('status', 'succeeded')
            ->sortByDesc('captured_at')
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'This post has no successful listing payment.',
            ], 422);
        }

        DB::transaction(function () use ($post, $payment) {
            $post->update([
                'status' => 'approved',
                'rejection_reason' => null,
                'expires_at' => now()->addDays($payment->duration_days),
            ]);
        });

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
        $post = GrowPost::where('status', 'pending')->findOrFail($id);
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
        $post = GrowPost::where('status', 'approved')->findOrFail($id);
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
