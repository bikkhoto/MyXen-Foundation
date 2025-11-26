<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKycVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $minLevel = 1): Response
    {
        if (!$request->user() || $request->user()->kyc_level < $minLevel) {
            return response()->json([
                'success' => false,
                'message' => 'KYC verification required. Your current level is insufficient.',
                'current_level' => $request->user()?->kyc_level ?? 0,
                'required_level' => $minLevel,
            ], 403);
        }

        return $next($request);
    }
}
