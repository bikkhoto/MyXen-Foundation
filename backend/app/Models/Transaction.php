<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="Transaction",
 *     @OA\Property(property="uuid", type="string", format="uuid", description="Transaction UUID"),
 *     @OA\Property(property="type", type="string", enum={"deposit", "withdrawal", "transfer", "payment", "refund", "fee"}),
 *     @OA\Property(property="direction", type="string", enum={"in", "out"}),
 *     @OA\Property(property="amount", type="number", format="float"),
 *     @OA\Property(property="fee", type="number", format="float"),
 *     @OA\Property(property="currency", type="string"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed", "cancelled"}),
 *     @OA\Property(property="blockchain_tx", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true)
 * )
 */
class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'wallet_id',
        'user_id',
        'type',
        'direction',
        'amount',
        'fee',
        'currency',
        'status',
        'reference',
        'blockchain_tx',
        'to_address',
        'from_address',
        'related_transaction_id',
        'description',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:9',
        'fee' => 'decimal:9',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'related_transaction_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'metadata' => array_merge($this->metadata ?? [], ['failure_reason' => $reason]),
        ]);
    }
}
