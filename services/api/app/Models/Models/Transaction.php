<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transaction Model
 *
 * Records all wallet debits and credits with blockchain references.
 *
 * @property int $id
 * @property int $wallet_id
 * @property int|null $counterparty_wallet_id
 * @property string $amount Decimal stored as string for precision
 * @property string $type Enum: debit, credit
 * @property string $status Enum: pending, completed, failed
 * @property string|null $external_tx Blockchain transaction signature
 * @property string|null $reference Unique idempotency reference
 * @property string|null $memo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'wallet_id',
        'counterparty_wallet_id',
        'amount',
        'type',
        'status',
        'external_tx',
        'reference',
        'memo',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:9',
        'wallet_id' => 'integer',
        'counterparty_wallet_id' => 'integer',
    ];

    /**
     * Get the wallet that owns this transaction.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the counterparty wallet for this transaction.
     */
    public function counterpartyWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'counterparty_wallet_id');
    }

    /**
     * Mark the transaction as completed.
     *
     * @param string|null $externalTx
     * @return bool
     */
    public function markCompleted(?string $externalTx = null): bool
    {
        $this->status = 'completed';

        if ($externalTx !== null) {
            $this->external_tx = $externalTx;
        }

        return $this->save();
    }

    /**
     * Mark the transaction as failed.
     *
     * @return bool
     */
    public function markFailed(): bool
    {
        $this->status = 'failed';
        return $this->save();
    }

    /**
     * Check if transaction is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
