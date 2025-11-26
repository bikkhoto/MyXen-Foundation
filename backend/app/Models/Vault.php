<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vault extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'balance',
        'myxn_balance',
        'lock_until',
        'auto_lock_days',
        'interest_rate',
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
        'myxn_balance' => 'decimal:9',
        'lock_until' => 'datetime',
        'auto_lock_days' => 'integer',
        'interest_rate' => 'decimal:4',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the vault.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if vault is locked.
     */
    public function isLocked(): bool
    {
        if (!$this->lock_until) {
            return false;
        }
        return $this->lock_until->isFuture();
    }

    /**
     * Lock the vault for specified days.
     */
    public function lock(int $days): bool
    {
        $this->lock_until = now()->addDays($days);
        return $this->save();
    }

    /**
     * Deposit funds to vault.
     */
    public function deposit(float $amount, string $currency = 'SOL'): bool
    {
        if ($currency === 'MYXN') {
            $this->myxn_balance += $amount;
        } else {
            $this->balance += $amount;
        }
        return $this->save();
    }

    /**
     * Withdraw funds from vault.
     */
    public function withdraw(float $amount, string $currency = 'SOL'): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        if ($currency === 'MYXN') {
            if ($this->myxn_balance < $amount) {
                return false;
            }
            $this->myxn_balance -= $amount;
        } else {
            if ($this->balance < $amount) {
                return false;
            }
            $this->balance -= $amount;
        }
        return $this->save();
    }

    /**
     * Calculate interest earnings.
     */
    public function calculateInterest(): float
    {
        $totalBalance = $this->balance + $this->myxn_balance;
        return $totalBalance * ($this->interest_rate / 100);
    }
}
