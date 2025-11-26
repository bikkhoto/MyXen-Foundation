<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Transaction",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="type", type="string", enum={"deposit", "withdrawal", "transfer", "payment", "refund", "fee"}),
 *     @OA\Property(property="amount", type="number", format="float"),
 *     @OA\Property(property="currency", type="string", example="SOL"),
 *     @OA\Property(property="fee", type="number", format="float"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed", "cancelled"}),
 *     @OA\Property(property="reference", type="string"),
 *     @OA\Property(property="solana_signature", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class TransactionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/transactions",
     *     tags={"Transactions"},
     *     summary="List user transactions",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Transactions retrieved")
     * )
     */
    public function index(Request $request)
    {
        $query = Transaction::where('user_id', $request->user()->id);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($transactions);
    }

    /**
     * @OA\Get(
     *     path="/transactions/{id}",
     *     tags={"Transactions"},
     *     summary="Get transaction details",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Transaction retrieved")
     * )
     */
    public function show(Request $request, $id)
    {
        $transaction = Transaction::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        return $this->success($transaction);
    }

    /**
     * @OA\Get(
     *     path="/transactions/reference/{reference}",
     *     tags={"Transactions"},
     *     summary="Get transaction by reference",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Transaction retrieved")
     * )
     */
    public function showByReference(Request $request, $reference)
    {
        $transaction = Transaction::where('user_id', $request->user()->id)
            ->where('reference', $reference)
            ->firstOrFail();

        return $this->success($transaction);
    }

    /**
     * @OA\Get(
     *     path="/transactions/stats",
     *     tags={"Transactions"},
     *     summary="Get transaction statistics",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Statistics retrieved")
     * )
     */
    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        $stats = [
            'total_transactions' => Transaction::where('user_id', $userId)->count(),
            'total_deposits' => Transaction::where('user_id', $userId)->where('type', 'deposit')->sum('amount'),
            'total_withdrawals' => Transaction::where('user_id', $userId)->where('type', 'withdrawal')->sum('amount'),
            'total_payments' => Transaction::where('user_id', $userId)->where('type', 'payment')->sum('amount'),
            'total_fees_paid' => Transaction::where('user_id', $userId)->sum('fee'),
            'pending_count' => Transaction::where('user_id', $userId)->where('status', 'pending')->count(),
        ];

        return $this->success($stats);
    }
}
