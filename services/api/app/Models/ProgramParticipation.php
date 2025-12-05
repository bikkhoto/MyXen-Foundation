<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramParticipation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'financial_program_id',
        'wallet_id',
        'amount',
        'initial_amount',
        'status',
        'rewards_earned',
        'rewards_claimed',
        'pending_rewards',
        'last_reward_at',
        'enrolled_at',
        'maturity_at',
        'withdrawn_at',
        'enrollment_tx_hash',
        'withdrawal_tx_hash',
        'solana_wallet_address',
        'trace_id',
        'span_id',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:9',
        'initial_amount' => 'decimal:9',
        'rewards_earned' => 'decimal:9',
        'rewards_claimed' => 'decimal:9',
        'pending_rewards' => 'decimal:9',
        'last_reward_at' => 'datetime',
        'enrolled_at' => 'datetime',
        'maturity_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_MATURED = 'matured';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_LIQUIDATED = 'liquidated';

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the financial program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(FinancialProgram::class, 'financial_program_id');
    }

    /**
     * Get the wallet
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get token transactions for this participation
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(TokenTransaction::class, 'program_participation_id');
    }

    /**
     * Scope for active participations
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for matured participations
     */
    public function scopeMatured($query)
    {
        return $query->where('status', self::STATUS_MATURED);
    }

    /**
     * Scope for pending rewards
     */
    public function scopeWithPendingRewards($query)
    {
        return $query->where('pending_rewards', '>', 0);
    }

    /**
     * Scope for user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if participation is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if participation has matured
     */
    public function hasMatured(): bool
    {
        if (!$this->maturity_at) {
            return false;
        }

        return now()->gte($this->maturity_at);
    }

    /**
     * Check if early withdrawal applies
     */
    public function isEarlyWithdrawal(): bool
    {
        if (!$this->maturity_at) {
            return false;
        }

        return now()->lt($this->maturity_at);
    }

    /**
     * Get days until maturity
     */
    public function getDaysUntilMaturity(): int
    {
        if (!$this->maturity_at) {
            return 0;
        }

        $days = now()->diffInDays($this->maturity_at, false);
        return max(0, $days);
    }

    /**
     * Get total value (amount + pending rewards)
     */
    public function getTotalValue(): float
    {
        return $this->amount + $this->pending_rewards;
    }

    /**
     * Get claimable rewards
     */
    public function getClaimableRewards(): float
    {
        return $this->pending_rewards;
    }

    /**
     * Calculate current rewards based on program APY
     */
    public function calculateCurrentRewards(): float
    {
        if (!$this->isActive() || !$this->enrolled_at) {
            return 0;
        }

        $program = $this->program;
        $daysActive = $this->enrolled_at->diffInDays(now());
        
        return $program->calculateEstimatedRewards($this->amount, $daysActive);
    }

    /**
     * Add rewards to pending
     */
    public function addRewards(float $amount): void
    {
        $this->increment('rewards_earned', $amount);
        $this->increment('pending_rewards', $amount);
        $this->update(['last_reward_at' => now()]);
    }

    /**
     * Claim pending rewards
     */
    public function claimRewards(): float
    {
        $claimable = $this->pending_rewards;
        
        $this->update([
            'rewards_claimed' => $this->rewards_claimed + $claimable,
            'pending_rewards' => 0,
        ]);

        return $claimable;
    }

    /**
     * Activate participation after blockchain confirmation
     */
    public function activate(string $txHash): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'enrolled_at' => now(),
            'enrollment_tx_hash' => $txHash,
            'maturity_at' => $this->program->lock_period_days > 0 
                ? now()->addDays($this->program->lock_period_days) 
                : null,
        ]);

        $this->program->addParticipation($this->amount);
    }

    /**
     * Complete withdrawal
     */
    public function withdraw(string $txHash): void
    {
        $this->program->removeParticipation($this->amount);

        $this->update([
            'status' => self::STATUS_WITHDRAWN,
            'withdrawn_at' => now(),
            'withdrawal_tx_hash' => $txHash,
        ]);
    }

    /**
     * Cancel pending participation
     */
    public function cancel(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \Exception('Only pending participations can be cancelled');
        }

        $this->update(['status' => self::STATUS_CANCELLED]);
    }
}
