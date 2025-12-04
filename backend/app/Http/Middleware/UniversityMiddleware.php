<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UniversityMiddleware
{
    /**
     * Handle an incoming request - only allow university users.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isUniversity()) {
            return response()->json([
                'message' => 'Forbidden. University access required.',
            ], 403);
        }

        return $next($request);
    }
}
