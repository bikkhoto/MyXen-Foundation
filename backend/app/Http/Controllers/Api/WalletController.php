<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Wallet",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="solana_address", type="string", example="5yKHV...abc"),
 *     @OA\Property(property="balance", type="number", format="float", example=100.50),
 *     @OA\Property(property="myxn_balance", type="number", format="float", example=5000.00),
 *     @OA\Property(property="status", type="string", enum={"active", "frozen", "closed"}, example="active")
 * )
 */
class WalletController extends Controller
{
    /**
     * @OA\Get(
     *     path="/wallet",
     *     tags={"Wallet"},
     *     summary="Get user wallet",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Wallet retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Wallet")
     *         )
     *     )
     * )
     */
    public function show(Request $request)
    {
        $wallet = $request->user()->wallet;
        
        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $request->user()->id,
                'balance' => 0,
                'myxn_balance' => 0,
            ]);
        }

        return $this->success($wallet);
    }

    /**
     * @OA\Get(
     *     path="/wallet/balance",
     *     tags={"Wallet"},
     *     summary="Get wallet balance",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Balance retrieved successfully"
     *     )
     * )
     */
    public function balance(Request $request)
    {
        $wallet = $request->user()->wallet;

        return $this->success([
            'sol_balance' => $wallet->balance ?? 0,
            'myxn_balance' => $wallet->myxn_balance ?? 0,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/wallet/link-solana",
     *     tags={"Wallet"},
     *     summary="Link Solana address",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"solana_address"},
     *             @OA\Property(property="solana_address", type="string", example="5yKHV...")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Solana address linked successfully")
     * )
     */
    public function linkSolana(Request $request)
    {
        $validated = $request->validate([
            'solana_address' => 'required|string|min:32|max:44',
        ]);

        $wallet = $request->user()->wallet;
        $wallet->update(['solana_address' => $validated['solana_address']]);

        return $this->success($wallet, 'Solana address linked successfully');
    }

    /**
     * @OA\Post(
     *     path="/wallet/transfer",
     *     tags={"Wallet"},
     *     summary="Transfer funds",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"to_address", "amount", "currency"},
     *             @OA\Property(property="to_address", type="string"),
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="currency", type="string", enum={"SOL", "MYXN"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Transfer initiated")
     * )
     */
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'to_address' => 'required|string',
            'amount' => 'required|numeric|min:0.000000001',
            'currency' => 'required|in:SOL,MYXN',
        ]);

        $wallet = $request->user()->wallet;

        if (!$wallet->hasSufficientBalance($validated['amount'], $validated['currency'])) {
            return $this->error('Insufficient balance', 400);
        }

        // Calculate fee
        $feePercentage = config('solana.transaction_fee_percentage', 0.5);
        $fee = $validated['amount'] * ($feePercentage / 100);

        // Create transaction record
        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'wallet_id' => $wallet->id,
            'type' => 'transfer',
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'fee' => $fee,
            'status' => 'pending',
            'to_address' => $validated['to_address'],
            'from_address' => $wallet->solana_address,
        ]);

        // Deduct from wallet (in real implementation, this would happen after blockchain confirmation)
        $wallet->withdraw($validated['amount'] + $fee, $validated['currency']);

        return $this->success([
            'transaction' => $transaction,
        ], 'Transfer initiated');
    }

    /**
     * @OA\Get(
     *     path="/wallet/transactions",
     *     tags={"Wallet"},
     *     summary="Get wallet transactions",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Transactions retrieved")
     * )
     */
    public function transactions(Request $request)
    {
        $wallet = $request->user()->wallet;
        
        $query = Transaction::where('wallet_id', $wallet->id);
        
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->paginated($transactions);
    }
}
