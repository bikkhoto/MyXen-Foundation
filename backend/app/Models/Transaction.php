<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'wallet_id',
        'type',
        'amount',
        'currency',
        'fee',
        'status',
        'reference',
        'solana_signature',
        'from_address',
        'to_address',
        'merchant_id',
        'description',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:9',
        'fee' => 'decimal:9',
        'metadata' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->reference)) {
                $model->reference = 'TXN-' . strtoupper(Str::random(12));
            }
        });
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet that owns the transaction.
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the merchant for this transaction.
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Scope a query to only include pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Mark transaction as completed.
     */
    public function markAsCompleted(?string $signature = null): bool
    {
        $this->status = 'completed';
        if ($signature) {
            $this->solana_signature = $signature;
        }
        return $this->save();
    }

    /**
     * Mark transaction as failed.
     */
    public function markAsFailed(?string $reason = null): bool
    {
        $this->status = 'failed';
        if ($reason) {
            $metadata = $this->metadata ?? [];
            $metadata['failure_reason'] = $reason;
            $this->metadata = $metadata;
        }
        return $this->save();
    }
}
