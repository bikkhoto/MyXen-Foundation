<?php

namespace App\Services\Admin\Controllers;

use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\AdminRegisterRequest;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

/**
 * Admin Authentication Controller
 *
 * Handles admin registration, login, logout, and profile retrieval.
 */
class AdminAuthController extends Controller
{
    /**
     * Register a new admin (superadmin only).
     *
     * @param AdminRegisterRequest $request
     * @return JsonResponse
     */
    public function registerAdmin(AdminRegisterRequest $request): JsonResponse
    {
        // Authorization check: Only superadmin can register admins
        $currentAdmin = $request->user('admin');

        if (!$currentAdmin || !$currentAdmin->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only superadmin can register new admins.',
            ], 403);
        }

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin registered successfully.',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'created_at' => $admin->created_at,
                ],
            ],
        ], 201);
    }

    /**
     * Login an admin.
     *
     * @param AdminLoginRequest $request
     * @return JsonResponse
     */
    public function loginAdmin(AdminLoginRequest $request): JsonResponse
    {
        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Admin logged in successfully.',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
                'token' => $token,
            ],
        ], 200);
    }

    /**
     * Logout the authenticated admin.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logoutAdmin(Request $request): JsonResponse
    {
        $request->user('admin')->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin logged out successfully.',
        ], 200);
    }

    /**
     * Get the authenticated admin's profile.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        return response()->json([
            'success' => true,
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'created_at' => $admin->created_at,
                ],
            ],
        ], 200);
    }
}
