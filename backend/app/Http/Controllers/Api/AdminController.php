<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Merchant;
use App\Models\KycDocument;
use App\Models\UniversityId;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/dashboard",
     *     tags={"Admin"},
     *     summary="Get admin dashboard stats",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Dashboard stats retrieved")
     * )
     */
    public function dashboard()
    {
        return $this->success([
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'new_today' => User::whereDate('created_at', today())->count(),
            ],
            'merchants' => [
                'total' => Merchant::count(),
                'active' => Merchant::where('status', 'active')->count(),
                'pending' => Merchant::where('status', 'pending')->count(),
            ],
            'transactions' => [
                'total' => Transaction::count(),
                'completed' => Transaction::where('status', 'completed')->count(),
                'pending' => Transaction::where('status', 'pending')->count(),
                'total_volume_sol' => Transaction::where('currency', 'SOL')->where('status', 'completed')->sum('amount'),
                'total_volume_myxn' => Transaction::where('currency', 'MYXN')->where('status', 'completed')->sum('amount'),
                'total_fees' => Transaction::where('status', 'completed')->sum('fee'),
            ],
            'kyc' => [
                'pending' => KycDocument::where('status', 'pending')->count(),
                'verified' => KycDocument::where('status', 'verified')->count(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/users",
     *     tags={"Admin"},
     *     summary="List all users",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="role", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Users retrieved")
     * )
     */
    public function users(Request $request)
    {
        $query = User::with(['wallet', 'merchant']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($users);
    }

    /**
     * @OA\Get(
     *     path="/admin/users/{id}",
     *     tags={"Admin"},
     *     summary="Get user details",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User retrieved")
     * )
     */
    public function showUser($id)
    {
        $user = User::with(['wallet', 'merchant', 'kycDocuments', 'vault'])
            ->findOrFail($id);

        return $this->success($user);
    }

    /**
     * @OA\Put(
     *     path="/admin/users/{id}",
     *     tags={"Admin"},
     *     summary="Update user",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="role", type="string"),
     *             @OA\Property(property="kyc_level", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="User updated")
     * )
     */
    public function updateUser(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:active,inactive,suspended',
            'role' => 'sometimes|in:user,merchant,admin',
            'kyc_level' => 'sometimes|integer|min:0|max:3',
        ]);

        $user = User::findOrFail($id);
        $user->update($validated);

        return $this->success($user, 'User updated successfully');
    }

    /**
     * @OA\Get(
     *     path="/admin/merchants",
     *     tags={"Admin"},
     *     summary="List all merchants",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Merchants retrieved")
     * )
     */
    public function merchants(Request $request)
    {
        $query = Merchant::with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $merchants = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($merchants);
    }

    /**
     * @OA\Put(
     *     path="/admin/merchants/{id}",
     *     tags={"Admin"},
     *     summary="Update merchant",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="commission_rate", type="number")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Merchant updated")
     * )
     */
    public function updateMerchant(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,active,suspended,closed',
            'commission_rate' => 'sometimes|numeric|min:0|max:10',
        ]);

        $merchant = Merchant::findOrFail($id);
        $merchant->update($validated);

        return $this->success($merchant, 'Merchant updated successfully');
    }

    /**
     * @OA\Get(
     *     path="/admin/transactions",
     *     tags={"Admin"},
     *     summary="List all transactions",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Transactions retrieved")
     * )
     */
    public function transactions(Request $request)
    {
        $query = Transaction::with(['user', 'merchant']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($transactions);
    }

    /**
     * @OA\Get(
     *     path="/admin/kyc/pending",
     *     tags={"Admin"},
     *     summary="List pending KYC documents",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Documents retrieved")
     * )
     */
    public function pendingKyc(Request $request)
    {
        $documents = KycDocument::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($documents);
    }

    /**
     * @OA\Post(
     *     path="/admin/kyc/{id}/approve",
     *     tags={"Admin"},
     *     summary="Approve KYC document",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Document approved")
     * )
     */
    public function approveKyc($id)
    {
        $document = KycDocument::findOrFail($id);
        $document->approve();

        // Update user KYC level
        $user = $document->user;
        $verifiedDocs = $user->kycDocuments()->where('status', 'verified')->count();
        $newLevel = min($verifiedDocs, 3);
        $user->update(['kyc_level' => $newLevel]);

        return $this->success($document, 'Document approved successfully');
    }

    /**
     * @OA\Post(
     *     path="/admin/kyc/{id}/reject",
     *     tags={"Admin"},
     *     summary="Reject KYC document",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Document rejected")
     * )
     */
    public function rejectKyc(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $document = KycDocument::findOrFail($id);
        $document->reject($validated['reason']);

        return $this->success($document, 'Document rejected');
    }

    /**
     * @OA\Get(
     *     path="/admin/university/pending",
     *     tags={"Admin"},
     *     summary="List pending university IDs",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="University IDs retrieved")
     * )
     */
    public function pendingUniversityIds(Request $request)
    {
        $universityIds = UniversityId::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($universityIds);
    }

    /**
     * @OA\Post(
     *     path="/admin/university/{id}/verify",
     *     tags={"Admin"},
     *     summary="Verify university ID",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="University ID verified")
     * )
     */
    public function verifyUniversityId($id)
    {
        $universityId = UniversityId::findOrFail($id);
        $universityId->verify();

        return $this->success($universityId, 'University ID verified successfully');
    }
}
