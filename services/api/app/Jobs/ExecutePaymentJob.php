<?php

namespace App\Jobs;

use App\Models\Models\PaymentIntent;
use App\Models\Models\Transaction;
use App\Models\Models\Wallet;
use App\Services\Payments\PaymentService;
use App\Services\Payments\SolanaWorkerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExecutePaymentJob
 *
 * Handles asynchronous execution of payment transfers via Solana blockchain.
 * Implements idempotency, fund reservation, and automatic rollback on failure.
 *
 * @package App\Jobs
 */
class ExecutePaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The payment intent ID to execute.
     *
     * @var int
     */
    public int $paymentIntentId;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     *
     * @var array
     */
    public $backoff = [5, 30, 120];

    /**
     * Create a new job instance.
     *
     * @param int $paymentIntentId
     */
    public function __construct(int $paymentIntentId)
    {
        $this->paymentIntentId = $paymentIntentId;
    }

    /**
     * Execute the job.
     *
     * @param PaymentService $paymentService
     * @param SolanaWorkerClient $solanaClient
     * @return void
     */
    public function handle(PaymentService $paymentService, SolanaWorkerClient $solanaClient): void
    {
        $intent = PaymentIntent::with(['wallet', 'user'])->find($this->paymentIntentId);

        if (!$intent) {
            Log::error("PaymentIntent #{$this->paymentIntentId} not found.");
            return;
        }

        // Idempotency check: verify no completed transaction exists with same reference
        $existingCompleted = Transaction::where('reference', $intent->getReference())
            ->where('status', 'completed')
            ->exists();

        if ($existingCompleted) {
            Log::info("Payment already completed for reference: {$intent->getReference()}");
            $intent->markCompleted();
            return;
        }

        try {
            DB::beginTransaction();

            // Mark intent as executing
            $intent->markExecuting();

            $wallet = $intent->wallet;

            // Verify wallet still has sufficient balance
            if (!$wallet->hasSufficientBalance($intent->amount)) {
                throw new \Exception('Insufficient balance in wallet.');
            }

            // Create pending debit transaction
            $debitTx = $paymentService->recordTransaction(
                $wallet,
                $intent->amount,
                'debit',
                'pending',
                $intent->getReference(),
                'Payment to ' . $intent->receiver_wallet_address
            );

            // Debit from sender wallet
            if (!$wallet->debit($intent->amount)) {
                throw new \Exception('Failed to debit wallet.');
            }

            // Call Solana worker to perform blockchain transfer
            $solanaResponse = $solanaClient->transfer(
                config('payments.token_mint'),
                $intent->amount,
                $wallet->address,
                $intent->receiver_wallet_address,
                $intent->getReference()
            );

            if (!$solanaResponse['success']) {
                throw new \Exception(
                    'Solana transfer failed: ' . ($solanaResponse['error'] ?? 'Unknown error')
                );
            }

            // Mark debit transaction as completed
            $debitTx->markCompleted($solanaResponse['txSignature']);

            // Find or create receiver wallet
            $receiverWallet = Wallet::where('address', $intent->receiver_wallet_address)
                ->where('currency', $intent->currency)
                ->first();

            if ($receiverWallet) {
                // Credit receiver wallet
                $receiverWallet->credit($intent->amount);

                // Create credit transaction for receiver
                $paymentService->recordTransaction(
                    $receiverWallet,
                    $intent->amount,
                    'credit',
                    'completed',
                    $intent->getReference(),
                    'Payment from wallet ID ' . $wallet->id,
                    $solanaResponse['txSignature'],
                    $wallet->id
                );
            }

            // Mark intent as completed
            $intent->markCompleted();

            DB::commit();

            Log::info("Payment intent #{$intent->id} completed successfully. TX: {$solanaResponse['txSignature']}");
        } catch (\Exception $e) {
            DB::rollBack();

            // Release reserved funds by crediting back
            if (isset($wallet) && isset($debitTx)) {
                $wallet->credit($intent->amount);
                $debitTx->markFailed();
            }

            // Mark intent as failed
            $intent->markFailed();

            Log::error("Payment intent #{$intent->id} failed: " . $e->getMessage(), [
                'intent_id' => $intent->id,
                'reference' => $intent->getReference(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Rethrow to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ExecutePaymentJob permanently failed for intent #{$this->paymentIntentId}", [
            'intent_id' => $this->paymentIntentId,
            'error' => $exception->getMessage(),
        ]);

        // Mark intent as failed if it still exists
        $intent = PaymentIntent::find($this->paymentIntentId);
        if ($intent && $intent->status !== 'failed') {
            $intent->markFailed();
        }
    }
}
