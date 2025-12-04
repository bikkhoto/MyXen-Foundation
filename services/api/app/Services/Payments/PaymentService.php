<?php

namespace App\Services\Payments;

use App\Models\Models\PaymentIntent;
use App\Models\Models\Transaction;
use App\Models\Models\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * PaymentService
 *
 * Business logic for payment operations including fund reservation,
 * transaction recording, and transfer finalization.
 *
 * @package App\Services\Payments
 */
class PaymentService
{
    /**
     * Reserve funds for a payment intent.
     *
     * @param Wallet $wallet
     * @param string|float $amount
     * @param string $currency
     * @param string $receiverAddress
     * @param string|null $memo
     * @return PaymentIntent
     * @throws \Exception
     */
    public function reserveFunds(
        Wallet $wallet,
        $amount,
        string $currency,
        string $receiverAddress,
        ?string $memo = null
    ): PaymentIntent {
        return DB::transaction(function () use ($wallet, $amount, $currency, $receiverAddress, $memo) {
            // Create payment intent
            $intent = PaymentIntent::create([
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'currency' => $currency,
                'receiver_wallet_address' => $receiverAddress,
                'status' => 'created',
                'meta' => [
                    'memo' => $memo,
                    'reserved_at' => now()->toIso8601String(),
                ],
            ]);

            // Create pending debit transaction for fund reservation
            $this->recordTransaction(
                $wallet,
                $amount,
                'debit',
                'pending',
                $intent->getReference(),
                $memo ?? 'Payment intent #' . $intent->id
            );

            // Debit from wallet to reserve funds
            if (!$wallet->debit($amount)) {
                throw new \Exception('Failed to reserve funds from wallet.');
            }

            return $intent;
        });
    }

    /**
     * Release reserved funds back to wallet.
     *
     * @param Wallet $wallet
     * @param string|float $amount
     * @return bool
     */
    public function releaseFunds(Wallet $wallet, $amount): bool
    {
        return $wallet->credit($amount);
    }

    /**
     * Finalize a payment transfer.
     *
     * @param PaymentIntent $intent
     * @param string $txSignature
     * @return bool
     */
    public function finalizeTransfer(PaymentIntent $intent, string $txSignature): bool
    {
        return DB::transaction(function () use ($intent, $txSignature) {
            // Update all pending transactions with this reference
            Transaction::where('reference', $intent->getReference())
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'external_tx' => $txSignature,
                ]);

            // Mark intent as completed
            return $intent->markCompleted();
        });
    }

    /**
     * Record a transaction in the database.
     *
     * @param Wallet $wallet
     * @param string|float $amount
     * @param string $type debit|credit
     * @param string $status pending|completed|failed
     * @param string|null $reference
     * @param string|null $memo
     * @param string|null $externalTx
     * @param int|null $counterpartyWalletId
     * @return Transaction
     */
    public function recordTransaction(
        Wallet $wallet,
        $amount,
        string $type,
        string $status,
        ?string $reference = null,
        ?string $memo = null,
        ?string $externalTx = null,
        ?int $counterpartyWalletId = null
    ): Transaction {
        return Transaction::create([
            'wallet_id' => $wallet->id,
            'counterparty_wallet_id' => $counterpartyWalletId,
            'amount' => $amount,
            'type' => $type,
            'status' => $status,
            'external_tx' => $externalTx,
            'reference' => $reference,
            'memo' => $memo,
        ]);
    }
}
