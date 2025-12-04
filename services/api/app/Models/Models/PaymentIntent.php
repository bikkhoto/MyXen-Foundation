<?php

namespace App\Models\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * PaymentIntent Model
 *
 * Tracks payment workflow from creation to completion.
 *
 * @property int $id
 * @property int $user_id
 * @property int $wallet_id
 * @property string $amount Decimal stored as string for precision
 * @property string $currency
 * @property string $receiver_wallet_address
 * @property string $status Enum: created, ready, executing, completed, failed, cancelled
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class PaymentIntent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'wallet_id',
        'amount',
        'currency',
        'receiver_wallet_address',
        'status',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:9',
        'meta' => 'array',
        'user_id' => 'integer',
        'wallet_id' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID reference on creation
        static::creating(function ($paymentIntent) {
            if (!isset($paymentIntent->meta['reference'])) {
                $paymentIntent->meta = array_merge(
                    $paymentIntent->meta ?? [],
                    ['reference' => (string) Str::uuid()]
                );
            }
        });
    }

    /**
     * Get the user that owns the payment intent.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet for this payment intent.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the UUID reference for idempotency.
     *
     * @return string
     */
    public function getReference(): string
    {
        return $this->meta['reference'] ?? '';
    }

    /**
     * Mark the intent as ready for execution.
     *
     * @return bool
     */
    public function markReady(): bool
    {
        $this->status = 'ready';
        return $this->save();
    }

    /**
     * Mark the intent as executing.
     *
     * @return bool
     */
    public function markExecuting(): bool
    {
        $this->status = 'executing';
        return $this->save();
    }

    /**
     * Mark the intent as completed.
     *
     * @return bool
     */
    public function markCompleted(): bool
    {
        $this->status = 'completed';
        return $this->save();
    }

    /**
     * Mark the intent as failed.
     *
     * @return bool
     */
    public function markFailed(): bool
    {
        $this->status = 'failed';
        return $this->save();
    }

    /**
     * Mark the intent as cancelled.
     *
     * @return bool
     */
    public function markCancelled(): bool
    {
        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Check if intent can be executed.
     *
     * @return bool
     */
    public function canExecute(): bool
    {
        return in_array($this->status, ['created', 'ready']);
    }
}
