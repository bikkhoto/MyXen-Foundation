<?php

namespace App\Models\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wallet Model
 *
 * Represents a cryptocurrency wallet with balance tracking and transaction management.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $address
 * @property string $currency
 * @property string $balance Decimal stored as string for precision
 * @property string $status Enum: active, disabled
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Wallet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'address',
        'currency',
        'balance',
        'status',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'decimal:9',
        'metadata' => 'array',
        'user_id' => 'integer',
    ];

    /**
     * Get the user that owns the wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all transactions for this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Debit the wallet by the specified amount.
     *
     * @param string|float $amount
     * @return bool
     */
    public function debit($amount): bool
    {
        $newBalance = bcsub($this->balance, (string) $amount, 9);

        if (bccomp($newBalance, '0', 9) < 0) {
            return false;
        }

        $this->balance = $newBalance;
        return $this->save();
    }

    /**
     * Credit the wallet by the specified amount.
     *
     * @param string|float $amount
     * @return bool
     */
    public function credit($amount): bool
    {
        $this->balance = bcadd($this->balance, (string) $amount, 9);
        return $this->save();
    }

    /**
     * Check if wallet has sufficient balance.
     *
     * @param string|float $amount
     * @return bool
     */
    public function hasSufficientBalance($amount): bool
    {
        return bccomp($this->balance, (string) $amount, 9) >= 0;
    }

    /**
     * Check if wallet is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
