<?php

namespace App\Services\Admin\Controllers;

use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Admin Dashboard Controller
 *
 * Provides endpoints for admin panel operations including user management,
 * KYC verification, payment logs, and dashboard statistics.
 */
class AdminDashboardController extends Controller
{
    /**
     * List all users with pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listUsers(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $users = User::with('wallet')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users,
        ], 200);
    }

    /**
     * Show a specific user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function showUser(int $id): JsonResponse
    {
        $user = User::with(['wallet', 'wallet.transactions' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ],
        ], 200);
    }

    /**
     * List users with pending KYC verification.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listPendingKyc(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $pendingUsers = User::where('kyc_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $pendingUsers,
        ], 200);
    }

    /**
     * Show KYC document for a specific user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function showKycDocument(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'kyc_status' => $user->kyc_status,
                'kyc_submitted_at' => $user->created_at,
                'documents' => [
                    // Placeholder for KYC documents
                    // In real implementation, you would have a KYC documents table
                    'message' => 'KYC document storage not yet implemented. This would contain document URLs, IDs, etc.',
                ],
            ],
        ], 200);
    }

    /**
     * Approve KYC for a user.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function approveKyc(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('admin');

        // Only admin and superadmin can approve KYC
        if (!$admin->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin or superadmin can approve KYC.',
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $user->kyc_status = 'approved';
        $user->is_verified = true;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'KYC approved successfully.',
            'data' => [
                'user_id' => $user->id,
                'kyc_status' => $user->kyc_status,
                'is_verified' => $user->is_verified,
            ],
        ], 200);
    }

    /**
     * Reject KYC for a user.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function rejectKyc(Request $request, int $id): JsonResponse
    {
        $admin = $request->user('admin');

        // Only admin and superadmin can reject KYC
        if (!$admin->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin or superadmin can reject KYC.',
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user->kyc_status = 'rejected';
        $user->is_verified = false;
        $user->save();

        // In real implementation, you would store the rejection reason
        // and notify the user

        return response()->json([
            'success' => true,
            'message' => 'KYC rejected successfully.',
            'data' => [
                'user_id' => $user->id,
                'kyc_status' => $user->kyc_status,
                'is_verified' => $user->is_verified,
                'rejection_reason' => $request->reason,
            ],
        ], 200);
    }

    /**
     * List payment transaction logs with pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listPaymentLogs(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $transactions = Transaction::with(['wallet.user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ], 200);
    }

    /**
     * Get dashboard summary statistics.
     *
     * @return JsonResponse
     */
    public function getDashboardStats(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'verified_users' => User::where('is_verified', true)->count(),
            'pending_kyc' => User::where('kyc_status', 'pending')->count(),
            'total_transactions' => Transaction::count(),
            'completed_transactions' => Transaction::where('status', Transaction::STATUS_COMPLETED)->count(),
            'pending_transactions' => Transaction::where('status', Transaction::STATUS_PENDING)->count(),
            'failed_transactions' => Transaction::where('status', Transaction::STATUS_FAILED)->count(),
            'total_payment_intents' => PaymentIntent::count(),
            'completed_payments' => PaymentIntent::where('status', PaymentIntent::STATUS_COMPLETED)->count(),
            'pending_payments' => PaymentIntent::where('status', PaymentIntent::STATUS_PENDING)->count(),
            'failed_payments' => PaymentIntent::where('status', PaymentIntent::STATUS_FAILED)->count(),
            'total_transaction_volume' => Transaction::where('status', Transaction::STATUS_COMPLETED)
                ->where('type', Transaction::TYPE_DEBIT)
                ->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ], 200);
    }
}
