<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialProgram extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'apy_rate',
        'min_amount',
        'max_amount',
        'lock_period_days',
        'early_withdrawal_penalty',
        'total_pool_limit',
        'current_pool_size',
        'max_participants',
        'current_participants',
        'reward_frequency',
        'compound_enabled',
        'status',
        'is_featured',
        'starts_at',
        'ends_at',
        'meta',
    ];

    protected $casts = [
        'apy_rate' => 'decimal:4',
        'min_amount' => 'decimal:9',
        'max_amount' => 'decimal:9',
        'total_pool_limit' => 'decimal:9',
        'current_pool_size' => 'decimal:9',
        'compound_enabled' => 'boolean',
        'is_featured' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Program types
     */
    public const TYPE_STAKING = 'staking';
    public const TYPE_LENDING = 'lending';
    public const TYPE_SAVINGS = 'savings';
    public const TYPE_REWARDS = 'rewards';

    /**
     * Status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ENDED = 'ended';
    public const STATUS_COMING_SOON = 'coming_soon';

    /**
     * Reward frequency constants
     */
    public const REWARD_DAILY = 'daily';
    public const REWARD_WEEKLY = 'weekly';
    public const REWARD_MONTHLY = 'monthly';
    public const REWARD_ON_MATURITY = 'on_maturity';

    /**
     * Get participations for this program
     */
    public function participations(): HasMany
    {
        return $this->hasMany(ProgramParticipation::class);
    }

    /**
     * Get active participations
     */
    public function activeParticipations(): HasMany
    {
        return $this->hasMany(ProgramParticipation::class)->where('status', 'active');
    }

    /**
     * Scope for active programs
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for featured programs
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if program is currently active
     */
    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = now();
        
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Check if program has available capacity
     */
    public function hasCapacity(): bool
    {
        if ($this->max_participants && $this->current_participants >= $this->max_participants) {
            return false;
        }

        if ($this->total_pool_limit && $this->current_pool_size >= $this->total_pool_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if amount is within program limits
     */
    public function isAmountValid(float $amount): bool
    {
        if ($amount < $this->min_amount) {
            return false;
        }

        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }

        return true;
    }

    /**
     * Calculate daily reward rate
     */
    public function getDailyRate(): float
    {
        return $this->apy_rate / 365;
    }

    /**
     * Calculate estimated rewards for amount and duration
     */
    public function calculateEstimatedRewards(float $amount, int $days): float
    {
        $dailyRate = $this->getDailyRate() / 100;
        
        if ($this->compound_enabled) {
            return $amount * (pow(1 + $dailyRate, $days) - 1);
        }

        return $amount * $dailyRate * $days;
    }

    /**
     * Increment participant count and pool size
     */
    public function addParticipation(float $amount): void
    {
        $this->increment('current_participants');
        $this->increment('current_pool_size', $amount);
    }

    /**
     * Decrement participant count and pool size
     */
    public function removeParticipation(float $amount): void
    {
        $this->decrement('current_participants');
        $this->decrement('current_pool_size', $amount);
    }
}
