<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TokenTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'type',
        'from_address',
        'to_address',
        'amount',
        'fee_amount',
        'service_wallet',
        'status',
        'tx_hash',
        'block_hash',
        'block_number',
        'confirmations',
        'network',
        'token_mint',
        'trace_id',
        'span_id',
        'parent_span_id',
        'error_message',
        'retry_count',
        'program_participation_id',
        'reference_type',
        'reference_id',
        'meta',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:9',
        'fee_amount' => 'decimal:9',
        'meta' => 'array',
    ];

    /**
     * Transaction types
     */
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_MINT = 'mint';
    public const TYPE_BURN = 'burn';
    public const TYPE_STAKE = 'stake';
    public const TYPE_UNSTAKE = 'unstake';
    public const TYPE_REWARD = 'reward';
    public const TYPE_PLATFORM_FEE = 'platform_fee';
    public const TYPE_DISTRIBUTION = 'distribution';
    public const TYPE_LENDING_DEPOSIT = 'lending_deposit';
    public const TYPE_LENDING_WITHDRAW = 'lending_withdraw';
    public const TYPE_SAVINGS_DEPOSIT = 'savings_deposit';
    public const TYPE_SAVINGS_WITHDRAW = 'savings_withdraw';

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Service wallet types
     */
    public const WALLET_TREASURY = 'treasury';
    public const WALLET_BURN = 'burn';
    public const WALLET_CHARITY = 'charity';
    public const WALLET_HR = 'hr';
    public const WALLET_MARKETING = 'marketing';

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the program participation
     */
    public function participation(): BelongsTo
    {
        return $this->belongsTo(ProgramParticipation::class, 'program_participation_id');
    }

    /**
     * Get the referenced model (polymorphic)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope confirmed transactions
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope by service wallet
     */
    public function scopeForServiceWallet($query, string $wallet)
    {
        return $query->where('service_wallet', $wallet);
    }

    /**
     * Scope by trace ID
     */
    public function scopeWithTraceId($query, string $traceId)
    {
        return $query->where('trace_id', $traceId);
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transaction is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Check if transaction failed
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark as processing
     */
    public function markProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    /**
     * Mark as confirmed
     */
    public function markConfirmed(string $txHash, ?string $blockHash = null, ?int $blockNumber = null): void
    {
        $this->update([
            'status' => self::STATUS_CONFIRMED,
            'tx_hash' => $txHash,
            'block_hash' => $blockHash,
            'block_number' => $blockNumber,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Increment retry count
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }

    /**
     * Update confirmations
     */
    public function updateConfirmations(int $count): void
    {
        $this->update(['confirmations' => $count]);
    }

    /**
     * Get net amount (amount - fee)
     */
    public function getNetAmount(): float
    {
        return $this->amount - $this->fee_amount;
    }

    /**
     * Get Solscan URL for transaction
     */
    public function getSolscanUrl(): ?string
    {
        if (!$this->tx_hash) {
            return null;
        }

        $network = $this->network === 'mainnet-beta' ? '' : "?cluster={$this->network}";
        return "https://solscan.io/tx/{$this->tx_hash}{$network}";
    }

    /**
     * Create a transfer transaction
     */
    public static function createTransfer(
        int $userId,
        string $fromAddress,
        string $toAddress,
        float $amount,
        float $fee = 0,
        ?string $traceId = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'type' => self::TYPE_TRANSFER,
            'from_address' => $fromAddress,
            'to_address' => $toAddress,
            'amount' => $amount,
            'fee_amount' => $fee,
            'status' => self::STATUS_PENDING,
            'network' => config('myxn.network', 'mainnet-beta'),
            'token_mint' => config('myxn.token.mint'),
            'trace_id' => $traceId,
        ]);
    }

    /**
     * Create a service wallet distribution
     */
    public static function createDistribution(
        string $serviceWallet,
        string $toAddress,
        float $amount,
        ?string $traceId = null
    ): self {
        return self::create([
            'type' => self::TYPE_DISTRIBUTION,
            'from_address' => config('myxn.wallets.treasury.address'),
            'to_address' => $toAddress,
            'amount' => $amount,
            'service_wallet' => $serviceWallet,
            'status' => self::STATUS_PENDING,
            'network' => config('myxn.network', 'mainnet-beta'),
            'token_mint' => config('myxn.token.mint'),
            'trace_id' => $traceId,
        ]);
    }
}
