<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->isAdmin() || $user->status !== 'pending_payment') {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Please complete your subscription to access the platform.',
            'code' => 'SUBSCRIPTION_REQUIRED',
            'redirectTo' => '/checkout',
        ], Response::HTTP_PAYMENT_REQUIRED);
    }
}
