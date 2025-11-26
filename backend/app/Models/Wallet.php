<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'solana_address',
        'balance',
        'myxn_balance',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'decimal:9',
        'myxn_balance' => 'decimal:9',
    ];

    /**
     * Get the user that owns the wallet.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet's transactions.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Deposit funds to wallet.
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
     * Withdraw funds from wallet.
     */
    public function withdraw(float $amount, string $currency = 'SOL'): bool
    {
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
     * Check if wallet has sufficient balance.
     */
    public function hasSufficientBalance(float $amount, string $currency = 'SOL'): bool
    {
        if ($currency === 'MYXN') {
            return $this->myxn_balance >= $amount;
        }
        return $this->balance >= $amount;
    }
}
