<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="Wallet",
 *     @OA\Property(property="id", type="integer", description="Wallet ID"),
 *     @OA\Property(property="address", type="string", description="Blockchain wallet address"),
 *     @OA\Property(property="currency", type="string", description="Currency code (e.g., MYXN)"),
 *     @OA\Property(property="balance", type="number", format="float", description="Available balance"),
 *     @OA\Property(property="pending_balance", type="number", format="float", description="Pending balance"),
 *     @OA\Property(property="is_primary", type="boolean", description="Is this the primary wallet"),
 *     @OA\Property(property="is_active", type="boolean", description="Is wallet active"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Wallet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'address',
        'public_key',
        'currency',
        'balance',
        'pending_balance',
        'is_primary',
        'is_active',
        'status',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'decimal:9',
        'pending_balance' => 'decimal:9',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'public_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function credit(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    public function debit(float $amount): void
    {
        $this->decrement('balance', $amount);
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }
}
