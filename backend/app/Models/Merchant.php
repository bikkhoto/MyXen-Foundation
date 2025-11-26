<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Merchant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'business_name',
        'business_type',
        'business_registration',
        'qr_code',
        'wallet_address',
        'status',
        'commission_rate',
        'description',
        'logo_url',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'commission_rate' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->qr_code)) {
                $model->qr_code = 'MYXEN-' . strtoupper(Str::random(16));
            }
        });
    }

    /**
     * Get the user that owns the merchant.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the merchant's transactions.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Check if merchant is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Generate new QR code.
     */
    public function regenerateQrCode(): bool
    {
        $this->qr_code = 'MYXEN-' . strtoupper(Str::random(16));
        return $this->save();
    }
}
