<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IsStudio
{
    public function handle(Request $request, Closure $next)
    {
        
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (isset($user->isDeleted) && $user->isDeleted) {
            return response()->json([
                'success' => false,
                'message' => 'Account suspended.',
            ], 403);
        }

        if ($user->role !== 'studio') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Studio access only.',
            ], 403);
        }

        return $next($request);
    }
}