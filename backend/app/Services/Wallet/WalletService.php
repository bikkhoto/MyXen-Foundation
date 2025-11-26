<?php

namespace App\Services\Wallet;

use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\User;

class WalletService
{
    /**
     * Create a wallet for a user.
     */
    public function createWallet(User $user): Wallet
    {
        return Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'myxn_balance' => 0,
            'status' => 'active',
        ]);
    }

    /**
     * Get or create wallet for user.
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        return $user->wallet ?? $this->createWallet($user);
    }

    /**
     * Process internal transfer.
     */
    public function internalTransfer(
        Wallet $fromWallet,
        Wallet $toWallet,
        float $amount,
        string $currency = 'SOL'
    ): ?Transaction {
        if (!$fromWallet->hasSufficientBalance($amount, $currency)) {
            return null;
        }

        $fee = $this->calculateFee($amount);

        // Deduct from sender
        $fromWallet->withdraw($amount + $fee, $currency);

        // Add to recipient
        $toWallet->deposit($amount, $currency);

        // Create transaction record
        return Transaction::create([
            'user_id' => $fromWallet->user_id,
            'wallet_id' => $fromWallet->id,
            'type' => 'transfer',
            'amount' => $amount,
            'currency' => $currency,
            'fee' => $fee,
            'status' => 'completed',
            'from_address' => $fromWallet->solana_address,
            'to_address' => $toWallet->solana_address,
        ]);
    }

    /**
     * Calculate transaction fee.
     */
    public function calculateFee(float $amount): float
    {
        $feePercentage = config('solana.transaction_fee_percentage', 0.5);
        return $amount * ($feePercentage / 100);
    }

    /**
     * Check daily limit for user.
     */
    public function checkDailyLimit(User $user, float $amount): bool
    {
        $kycLevels = config('kyc.levels');
        $dailyLimit = $kycLevels[$user->kyc_level]['daily_limit'] ?? 100;

        $todayTotal = Transaction::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('amount');

        return ($todayTotal + $amount) <= $dailyLimit;
    }

    /**
     * Freeze wallet.
     */
    public function freezeWallet(Wallet $wallet): bool
    {
        $wallet->status = 'frozen';
        return $wallet->save();
    }

    /**
     * Unfreeze wallet.
     */
    public function unfreezeWallet(Wallet $wallet): bool
    {
        $wallet->status = 'active';
        return $wallet->save();
    }
}
