<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\Blockchain\SolanaServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWithdrawal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public Transaction $transaction
    ) {
        $this->onQueue('withdrawals');
    }

    public function handle(SolanaServiceInterface $solanaService): void
    {
        Log::info("Processing withdrawal for transaction {$this->transaction->uuid}");

        try {
            $this->transaction->update(['status' => 'processing']);

            $wallet = $this->transaction->wallet;

            // TODO: Implement actual Solana transaction signing and sending
            // This requires:
            // 1. Secure access to the hot wallet private key
            // 2. Building the transfer instruction
            // 3. Signing the transaction
            // 4. Submitting to the Solana network

            $txSignature = $solanaService->sendTransaction(
                $wallet->address,
                $this->transaction->to_address,
                $this->transaction->amount,
                "Withdrawal: {$this->transaction->uuid}"
            );

            $this->transaction->update([
                'blockchain_tx' => $txSignature,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Move from pending balance (already deducted from available)
            $wallet->decrement('pending_balance', $this->transaction->amount + $this->transaction->fee);

            Log::info("Withdrawal completed for transaction {$this->transaction->uuid}");

        } catch (\Exception $e) {
            Log::error("Withdrawal failed for transaction {$this->transaction->uuid}: {$e->getMessage()}");

            $this->transaction->markAsFailed($e->getMessage());

            // Refund the amount back to available balance
            $wallet = $this->transaction->wallet;
            $wallet->increment('balance', $this->transaction->amount + $this->transaction->fee);
            $wallet->decrement('pending_balance', $this->transaction->amount + $this->transaction->fee);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Withdrawal job failed permanently for transaction {$this->transaction->uuid}");

        $this->transaction->markAsFailed($exception->getMessage());

        // Ensure funds are refunded
        $wallet = $this->transaction->wallet;
        $wallet->increment('balance', $this->transaction->amount + $this->transaction->fee);
        $wallet->decrement('pending_balance', $this->transaction->amount + $this->transaction->fee);
    }
}
