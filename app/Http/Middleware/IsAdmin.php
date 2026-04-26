<?php 
namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;



class IsAdmin
{
      public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
 
        // Not authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }
 
        // Soft-deleted / banned account
        if (isset($user->isDeleted) && $user->isDeleted) {
            return response()->json([
                'success' => false,
                'message' => 'Account suspended.',
            ], 403);
        }
 
        // Not an admin
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access only.',
            ], 403);
        }
 
        return $next($request);
    }

}