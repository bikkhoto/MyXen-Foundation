<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SaleVoucher Model
 *
 * Represents a signed voucher issued for Solana presale purchases.
 * Each voucher contains buyer/sale public keys, allocation limits,
 * a unique nonce for replay protection, and an ed25519 signature.
 *
 * @property int $id
 * @property string $sale_pubkey Base58-encoded Solana sale config PDA
 * @property string $buyer_pubkey Base58-encoded Solana wallet address
 * @property int $max_allocation Maximum tokens buyer can purchase
 * @property int $nonce Unique nonce for replay protection
 * @property int $expiry_ts Unix timestamp when voucher expires
 * @property string $signature Base64-encoded ed25519 signature
 * @property int|null $issued_by Admin user ID who issued the voucher
 * @property \DateTime $issued_at When the voucher was issued
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class SaleVoucher extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sale_vouchers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'sale_pubkey',
        'buyer_pubkey',
        'max_allocation',
        'nonce',
        'expiry_ts',
        'signature',
        'issued_by',
        'issued_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'max_allocation' => 'integer',
        'nonce' => 'integer',
        'expiry_ts' => 'integer',
        'issued_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the admin who issued this voucher
     *
     * @return BelongsTo
     */
    public function issuedBy(): BelongsTo
    {
        // TODO: Update this relationship to match your Admin model
        // If you have a different admin model, adjust the namespace
        return $this->belongsTo(\App\Models\User::class, 'issued_by');
    }

    /**
     * Check if the voucher has expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expiry_ts < time();
    }

    /**
     * Get voucher data as array (for frontend consumption)
     *
     * @return array
     */
    public function toVoucherData(): array
    {
        return [
            'buyer' => $this->buyer_pubkey,
            'sale' => $this->sale_pubkey,
            'max_allocation' => $this->max_allocation,
            'nonce' => $this->nonce,
            'expiry_ts' => $this->expiry_ts,
        ];
    }

    /**
     * Generate a unique nonce using microseconds
     *
     * @return int
     */
    public static function generateNonce(): int
    {
        return (int) (microtime(true) * 1000000);
    }

    /**
     * Scope: Get vouchers by buyer
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $buyerPubkey
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByBuyer($query, string $buyerPubkey)
    {
        return $query->where('buyer_pubkey', $buyerPubkey);
    }

    /**
     * Scope: Get vouchers by sale
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $salePubkey
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySale($query, string $salePubkey)
    {
        return $query->where('sale_pubkey', $salePubkey);
    }

    /**
     * Scope: Get non-expired vouchers
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('expiry_ts', '>=', time());
    }

    /**
     * Scope: Get expired vouchers
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('expiry_ts', '<', time());
    }
}
