<?php

namespace App\Jobs;

use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\Wallet;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecutePayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $intentId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $intent = PaymentIntent::find($this->intentId);

        if (!$intent) {
            Log::error("Payment intent not found: {$this->intentId}");
            return;
        }

        // Check if already completed
        if ($intent->status === PaymentIntent::STATUS_COMPLETED) {
            Log::info("Payment intent already completed: {$this->intentId}");
            return;
        }

        try {
            DB::beginTransaction();

            // Get sender and receiver wallets
            $senderWallet = Wallet::where('user_id', $intent->sender_id)
                ->where('currency', $intent->currency)
                ->lockForUpdate()
                ->first();

            $receiverWallet = Wallet::where('user_id', $intent->receiver_id)
                ->where('currency', $intent->currency)
                ->lockForUpdate()
                ->first();

            // Validate sender wallet exists
            if (!$senderWallet) {
                throw new Exception("Sender wallet not found for user {$intent->sender_id}");
            }

            // Check sufficient balance
            if (!$senderWallet->hasSufficientBalance($intent->amount)) {
                throw new Exception("Insufficient balance. Required: {$intent->amount}, Available: {$senderWallet->balance}");
            }

            // Create or get receiver wallet if it doesn't exist
            if (!$receiverWallet) {
                $receiverWallet = Wallet::create([
                    'user_id' => $intent->receiver_id,
                    'currency' => $intent->currency,
                    'balance' => 0,
                ]);
                Log::info("Created receiver wallet for user {$intent->receiver_id}");
            }

            // Create debit transaction for sender
            $debitTransaction = Transaction::create([
                'wallet_id' => $senderWallet->id,
                'amount' => $intent->amount,
                'type' => Transaction::TYPE_DEBIT,
                'status' => Transaction::STATUS_PENDING,
                'reference' => $intent->reference,
                'description' => "Payment to user {$intent->receiver_id}",
            ]);

            // Debit sender wallet
            if (!$senderWallet->debit($intent->amount)) {
                throw new Exception("Failed to debit sender wallet");
            }

            // Mark debit transaction as completed
            $debitTransaction->markAsCompleted();

            // Create credit transaction for receiver
            $creditTransaction = Transaction::create([
                'wallet_id' => $receiverWallet->id,
                'amount' => $intent->amount,
                'type' => Transaction::TYPE_CREDIT,
                'status' => Transaction::STATUS_PENDING,
                'reference' => $intent->reference,
                'description' => "Payment from user {$intent->sender_id}",
            ]);

            // Credit receiver wallet
            if (!$receiverWallet->credit($intent->amount)) {
                throw new Exception("Failed to credit receiver wallet");
            }

            // Mark credit transaction as completed
            $creditTransaction->markAsCompleted();

            // Mark payment intent as completed
            $intent->markAsCompleted();

            DB::commit();

            Log::info("Payment executed successfully: Intent {$this->intentId}, Amount: {$intent->amount} {$intent->currency}");
        } catch (Exception $e) {
            DB::rollBack();

            Log::error("Payment execution failed: Intent {$this->intentId}, Error: {$e->getMessage()}");

            // Mark intent as failed
            $intent->markAsFailed();

            // Mark any pending transactions as failed
            if (isset($debitTransaction) && $debitTransaction->isPending()) {
                $debitTransaction->markAsFailed();
            }

            if (isset($creditTransaction) && $creditTransaction->isPending()) {
                $creditTransaction->markAsFailed();
            }

            // Re-throw exception to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error("Payment job failed permanently: Intent {$this->intentId}, Error: {$exception->getMessage()}");

        $intent = PaymentIntent::find($this->intentId);
        if ($intent && $intent->status !== PaymentIntent::STATUS_FAILED) {
            $intent->markAsFailed();
        }
    }
}
