<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     *
     * Checks if the authenticated admin has the required role.
     * Usage: ->middleware('admin.role:superadmin')
     *        ->middleware('admin.role:admin,moderator')
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = $request->user('admin');

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (empty($roles)) {
            // No specific role required, just needs to be an admin
            return $next($request);
        }

        // Check if admin has any of the required roles
        if (!in_array($admin->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Insufficient permissions.',
                'required_role' => $roles,
                'your_role' => $admin->role,
            ], 403);
        }

        return $next($request);
    }
}
