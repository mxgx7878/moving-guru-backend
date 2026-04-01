<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProfileView;
use App\Models\User;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class ProfileViewController extends Controller
{
    public function store(Request $request, $userId)
    {
        $viewer = $request->user();

        if ($viewer->id == $userId) {
            return ApiResponse::error('Cannot view your own profile', [], 422);
        }

        $viewedUser = User::findOrFail($userId);

        ProfileView::create([
            'viewer_id' => $viewer->id,
            'viewed_user_id' => $viewedUser->id,
            'viewed_at' => now(),
        ]);

        return ApiResponse::success('Profile view recorded');
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $views = ProfileView::where('viewed_user_id', $user->id)
            ->with('viewer:id,name,email')
            ->orderBy('viewed_at', 'desc')
            ->paginate(20);

        return ApiResponse::success('Profile views fetched', $views);
    }

    public function analytics(Request $request)
    {
        $user = $request->user();

        $totalViews = ProfileView::where('viewed_user_id', $user->id)->count();
        $uniqueViewers = ProfileView::where('viewed_user_id', $user->id)->distinct('viewer_id')->count('viewer_id');
        $last30Days = ProfileView::where('viewed_user_id', $user->id)
            ->where('viewed_at', '>=', now()->subDays(30))
            ->count();
        $last7Days = ProfileView::where('viewed_user_id', $user->id)
            ->where('viewed_at', '>=', now()->subDays(7))
            ->count();

        return ApiResponse::success('Profile view analytics', [
            'total_views' => $totalViews,
            'unique_viewers' => $uniqueViewers,
            'last_30_days' => $last30Days,
            'last_7_days' => $last7Days,
        ]);
    }
}
