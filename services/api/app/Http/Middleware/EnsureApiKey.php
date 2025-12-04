<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure API Key Middleware
 *
 * Validates incoming requests have a valid API key in the X-API-KEY header.
 * Used to protect internal service endpoints.
 */
class EnsureApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-KEY');
        $validApiKey = config('notifications.api_key');

        if (empty($validApiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'API key authentication is not configured.',
            ], 500);
        }

        if (empty($apiKey) || $apiKey !== $validApiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid or missing API key.',
            ], 401);
        }

        return $next($request);
    }
}

