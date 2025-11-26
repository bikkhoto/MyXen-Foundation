<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vault;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Vault",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="My Vault"),
 *     @OA\Property(property="balance", type="number", format="float", example=100.50),
 *     @OA\Property(property="myxn_balance", type="number", format="float", example=5000.00),
 *     @OA\Property(property="lock_until", type="string", format="date-time"),
 *     @OA\Property(property="interest_rate", type="number", format="float", example=5.0),
 *     @OA\Property(property="status", type="string", enum={"active", "locked", "closed"})
 * )
 */
class VaultController extends Controller
{
    /**
     * @OA\Get(
     *     path="/vault",
     *     tags={"Vault"},
     *     summary="Get user vault",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Vault retrieved")
     * )
     */
    public function show(Request $request)
    {
        $vault = $request->user()->vault;

        if (!$vault) {
            $vault = Vault::create([
                'user_id' => $request->user()->id,
                'name' => 'My Vault',
                'balance' => 0,
                'myxn_balance' => 0,
                'interest_rate' => 3.5,
            ]);
        }

        return $this->success([
            'vault' => $vault,
            'is_locked' => $vault->isLocked(),
            'estimated_interest' => $vault->calculateInterest(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/vault/deposit",
     *     tags={"Vault"},
     *     summary="Deposit to vault",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "currency"},
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="currency", type="string", enum={"SOL", "MYXN"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Deposit successful")
     * )
     */
    public function deposit(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.000000001',
            'currency' => 'required|in:SOL,MYXN',
        ]);

        $user = $request->user();
        $wallet = $user->wallet;

        if (!$wallet->hasSufficientBalance($validated['amount'], $validated['currency'])) {
            return $this->error('Insufficient wallet balance', 400);
        }

        $vault = $user->vault;
        if (!$vault) {
            $vault = Vault::create([
                'user_id' => $user->id,
                'name' => 'My Vault',
                'balance' => 0,
                'myxn_balance' => 0,
                'interest_rate' => 3.5,
            ]);
        }

        // Transfer from wallet to vault
        $wallet->withdraw($validated['amount'], $validated['currency']);
        $vault->deposit($validated['amount'], $validated['currency']);

        return $this->success([
            'vault' => $vault->fresh(),
            'wallet_balance' => [
                'sol' => $wallet->balance,
                'myxn' => $wallet->myxn_balance,
            ],
        ], 'Deposit successful');
    }

    /**
     * @OA\Post(
     *     path="/vault/withdraw",
     *     tags={"Vault"},
     *     summary="Withdraw from vault",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "currency"},
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="currency", type="string", enum={"SOL", "MYXN"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Withdrawal successful")
     * )
     */
    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.000000001',
            'currency' => 'required|in:SOL,MYXN',
        ]);

        $user = $request->user();
        $vault = $user->vault;

        if (!$vault) {
            return $this->error('Vault not found', 404);
        }

        if ($vault->isLocked()) {
            return $this->error('Vault is locked until ' . $vault->lock_until->format('Y-m-d H:i:s'), 400);
        }

        if (!$vault->withdraw($validated['amount'], $validated['currency'])) {
            return $this->error('Insufficient vault balance', 400);
        }

        // Deposit to wallet
        $wallet = $user->wallet;
        $wallet->deposit($validated['amount'], $validated['currency']);

        return $this->success([
            'vault' => $vault->fresh(),
            'wallet_balance' => [
                'sol' => $wallet->balance,
                'myxn' => $wallet->myxn_balance,
            ],
        ], 'Withdrawal successful');
    }

    /**
     * @OA\Post(
     *     path="/vault/lock",
     *     tags={"Vault"},
     *     summary="Lock vault",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"days"},
     *             @OA\Property(property="days", type="integer", minimum=1, maximum=365)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Vault locked")
     * )
     */
    public function lock(Request $request)
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $vault = $request->user()->vault;

        if (!$vault) {
            return $this->error('Vault not found', 404);
        }

        if ($vault->isLocked()) {
            return $this->error('Vault is already locked', 400);
        }

        // Increase interest rate based on lock period
        $baseRate = 3.5;
        $bonusRate = $validated['days'] * 0.01; // 0.01% per day
        $newRate = min($baseRate + $bonusRate, 10); // Max 10%

        $vault->update([
            'interest_rate' => $newRate,
            'status' => 'locked',
        ]);
        $vault->lock($validated['days']);

        return $this->success([
            'vault' => $vault->fresh(),
            'lock_until' => $vault->lock_until,
            'new_interest_rate' => $newRate,
        ], 'Vault locked successfully');
    }

    /**
     * @OA\Put(
     *     path="/vault/settings",
     *     tags={"Vault"},
     *     summary="Update vault settings",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="auto_lock_days", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Settings updated")
     * )
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'auto_lock_days' => 'sometimes|nullable|integer|min:0|max:365',
        ]);

        $vault = $request->user()->vault;

        if (!$vault) {
            return $this->error('Vault not found', 404);
        }

        $vault->update($validated);

        return $this->success($vault, 'Settings updated successfully');
    }

    /**
     * @OA\Get(
     *     path="/vault/interest-rates",
     *     tags={"Vault"},
     *     summary="Get interest rate tiers",
     *     @OA\Response(response=200, description="Interest rates retrieved")
     * )
     */
    public function interestRates()
    {
        return $this->success([
            'base_rate' => 3.5,
            'tiers' => [
                ['days' => 30, 'rate' => 3.8],
                ['days' => 90, 'rate' => 4.4],
                ['days' => 180, 'rate' => 5.3],
                ['days' => 365, 'rate' => 7.15],
            ],
            'max_rate' => 10.0,
        ]);
    }
}
