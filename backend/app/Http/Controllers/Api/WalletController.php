<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\TransferRequest;
use App\Http\Requests\Wallet\WithdrawRequest;
use App\Jobs\ProcessWithdrawal;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Blockchain\SolanaServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Wallet",
 *     description="API endpoints for wallet management"
 * )
 */
class WalletController extends Controller
{
    public function __construct(
        protected SolanaServiceInterface $solanaService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/wallet",
     *     summary="Get user wallet balances",
     *     tags={"Wallet"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User wallet information",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="wallets",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Wallet")
     *             ),
     *             @OA\Property(
     *                 property="total_balance",
     *                 type="object",
     *                 @OA\Property(property="MYXN", type="number", example=100.5)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $wallets = $request->user()->wallets()->get();

        $totalBalance = $wallets->groupBy('currency')->map(function ($group) {
            return $group->sum('balance');
        });

        return response()->json([
            'wallets' => $wallets,
            'total_balance' => $totalBalance,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/wallet/{wallet}",
     *     summary="Get specific wallet details",
     *     tags={"Wallet"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="wallet",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wallet details",
     *         @OA\JsonContent(
     *             @OA\Property(property="wallet", ref="#/components/schemas/Wallet"),
     *             @OA\Property(
     *                 property="recent_transactions",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Transaction")
     *             )
     *         )
     *     )
     * )
     */
    public function show(Request $request, Wallet $wallet): JsonResponse
    {
        // Ensure user owns the wallet
        if ($wallet->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $transactions = $wallet->transactions()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'wallet' => $wallet,
            'recent_transactions' => $transactions,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/wallet/transfer",
     *     summary="Transfer funds internally",
     *     tags={"Wallet"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"to_address","amount"},
     *             @OA\Property(property="from_wallet_id", type="integer", description="Source wallet ID (defaults to primary)"),
     *             @OA\Property(property="to_address", type="string", description="Recipient wallet address"),
     *             @OA\Property(property="amount", type="number", example=10.5),
     *             @OA\Property(property="currency", type="string", example="MYXN"),
     *             @OA\Property(property="description", type="string", example="Payment for services")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer initiated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Transfer completed successfully"),
     *             @OA\Property(property="transaction", ref="#/components/schemas/Transaction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Insufficient balance or invalid request"
     *     )
     * )
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
        $user = $request->user();
        $currency = $request->input('currency', 'MYXN');

        // Get source wallet
        $fromWallet = $request->from_wallet_id
            ? $user->wallets()->findOrFail($request->from_wallet_id)
            : $user->primaryWallet;

        if (!$fromWallet) {
            return response()->json(['message' => 'No wallet found'], 400);
        }

        // Check balance
        if (!$fromWallet->hasSufficientBalance($request->amount)) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        // Find recipient wallet
        $toWallet = Wallet::where('address', $request->to_address)
            ->where('currency', $currency)
            ->first();

        if (!$toWallet) {
            return response()->json(['message' => 'Recipient wallet not found'], 404);
        }

        // Create outgoing transaction
        $outTransaction = Transaction::create([
            'wallet_id' => $fromWallet->id,
            'user_id' => $user->id,
            'type' => 'transfer',
            'direction' => 'out',
            'amount' => $request->amount,
            'currency' => $currency,
            'status' => 'completed',
            'to_address' => $toWallet->address,
            'from_address' => $fromWallet->address,
            'description' => $request->description,
            'completed_at' => now(),
        ]);

        // Create incoming transaction
        $inTransaction = Transaction::create([
            'wallet_id' => $toWallet->id,
            'user_id' => $toWallet->user_id,
            'type' => 'transfer',
            'direction' => 'in',
            'amount' => $request->amount,
            'currency' => $currency,
            'status' => 'completed',
            'to_address' => $toWallet->address,
            'from_address' => $fromWallet->address,
            'related_transaction_id' => $outTransaction->id,
            'description' => $request->description,
            'completed_at' => now(),
        ]);

        // Update balances
        $fromWallet->debit($request->amount);
        $toWallet->credit($request->amount);

        return response()->json([
            'message' => 'Transfer completed successfully',
            'transaction' => $outTransaction,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/wallet/withdraw",
     *     summary="Withdraw funds to blockchain",
     *     tags={"Wallet"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"to_address","amount"},
     *             @OA\Property(property="from_wallet_id", type="integer"),
     *             @OA\Property(property="to_address", type="string", description="External blockchain address"),
     *             @OA\Property(property="amount", type="number", example=10.5),
     *             @OA\Property(property="currency", type="string", example="MYXN")
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Withdrawal queued",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Withdrawal queued for processing"),
     *             @OA\Property(property="transaction", ref="#/components/schemas/Transaction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Insufficient balance"
     *     )
     * )
     */
    public function withdraw(WithdrawRequest $request): JsonResponse
    {
        $user = $request->user();
        $currency = $request->input('currency', 'MYXN');

        // Get source wallet
        $fromWallet = $request->from_wallet_id
            ? $user->wallets()->findOrFail($request->from_wallet_id)
            : $user->primaryWallet;

        if (!$fromWallet) {
            return response()->json(['message' => 'No wallet found'], 400);
        }

        // Check balance (including a fee buffer)
        $fee = 0.001; // TODO: Calculate dynamic fee
        $totalAmount = $request->amount + $fee;

        if (!$fromWallet->hasSufficientBalance($totalAmount)) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        // Create pending transaction
        $transaction = Transaction::create([
            'wallet_id' => $fromWallet->id,
            'user_id' => $user->id,
            'type' => 'withdrawal',
            'direction' => 'out',
            'amount' => $request->amount,
            'fee' => $fee,
            'currency' => $currency,
            'status' => 'pending',
            'to_address' => $request->to_address,
            'from_address' => $fromWallet->address,
        ]);

        // Hold the amount in pending balance
        $fromWallet->decrement('balance', $totalAmount);
        $fromWallet->increment('pending_balance', $totalAmount);

        // Queue the withdrawal job
        ProcessWithdrawal::dispatch($transaction);

        return response()->json([
            'message' => 'Withdrawal queued for processing',
            'transaction' => $transaction,
        ], 202);
    }

    /**
     * @OA\Get(
     *     path="/api/wallet/{wallet}/transactions",
     *     summary="Get wallet transaction history",
     *     tags={"Wallet"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="wallet",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction list",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="transactions",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Transaction")
     *             )
     *         )
     *     )
     * )
     */
    public function transactions(Request $request, Wallet $wallet): JsonResponse
    {
        if ($wallet->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $transactions = $wallet->transactions()
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($transactions);
    }
}
