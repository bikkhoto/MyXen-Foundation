<?php

namespace App\Services\MYXN;

use App\Models\User;
use App\Models\Wallet;
use App\Models\FinancialProgram;
use App\Models\ProgramParticipation;
use App\Services\MYXN\MYXNTokenService;
use App\Services\MYXN\TracingService;
use App\Services\MYXN\ServiceWalletManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * FinancialProgramService
 *
 * Manages MYXN financial programs including presale, staking,
 * rewards, and token burn mechanisms.
 *
 * @package App\Services\MYXN
 */
class FinancialProgramService
{
    /**
     * Program configurations.
     *
     * @var array
     */
    protected array $programs;

    /**
     * MYXN Token Service.
     *
     * @var MYXNTokenService
     */
    protected MYXNTokenService $tokenService;

    /**
     * Service wallet manager.
     *
     * @var ServiceWalletManager
     */
    protected ServiceWalletManager $walletManager;

    /**
     * Tracing service.
     *
     * @var TracingService
     */
    protected TracingService $tracer;

    /**
     * Create a new financial program service instance.
     */
    public function __construct(
        MYXNTokenService $tokenService,
        ServiceWalletManager $walletManager,
        TracingService $tracer
    ) {
        $this->programs = config('myxn.programs', []);
        $this->tokenService = $tokenService;
        $this->walletManager = $walletManager;
        $this->tracer = $tracer;
    }

    /*
    |--------------------------------------------------------------------------
    | Presale Program
    |--------------------------------------------------------------------------
    */

    /**
     * Process a presale purchase.
     *
     * @param User $user
     * @param float $usdAmount
     * @param string $paymentMethod
     * @return array
     */
    public function processPresalePurchase(User $user, float $usdAmount, string $paymentMethod): array
    {
        $span = $this->tracer->startSpan('myxn.presale.purchase', [
            'user_id' => $user->id,
            'usd_amount' => $usdAmount,
            'payment_method' => $paymentMethod,
        ]);

        try {
            // Validate presale is enabled
            if (!$this->programs['presale']['enabled']) {
                throw new \Exception('Presale is currently not active');
            }

            // Validate purchase limits
            $this->validatePresalePurchase($user, $usdAmount);

            // Calculate MYXN tokens
            $priceUsd = $this->programs['presale']['price_usd'];
            $tokenAmount = $usdAmount / $priceUsd;

            return DB::transaction(function () use ($user, $usdAmount, $tokenAmount, $paymentMethod, $span) {
                // Record the participation
                $participation = ProgramParticipation::create([
                    'user_id' => $user->id,
                    'program_type' => 'presale',
                    'usd_amount' => $usdAmount,
                    'token_amount' => $tokenAmount,
                    'status' => 'pending',
                    'payment_method' => $paymentMethod,
                    'metadata' => [
                        'price_per_token' => $this->programs['presale']['price_usd'],
                        'purchased_at' => now()->toIso8601String(),
                    ],
                ]);

                $this->tracer->addEvent($span, 'participation_created', [
                    'participation_id' => $participation->id,
                    'token_amount' => $tokenAmount,
                ]);

                Log::channel('myxn')->info('Presale purchase initiated', [
                    'user_id' => $user->id,
                    'participation_id' => $participation->id,
                    'usd_amount' => $usdAmount,
                    'token_amount' => $tokenAmount,
                ]);

                return [
                    'success' => true,
                    'participation_id' => $participation->id,
                    'token_amount' => $tokenAmount,
                    'usd_amount' => $usdAmount,
                    'price_per_token' => $priceUsd,
                    'status' => 'pending',
                ];
            });
        } catch (\Exception $e) {
            $this->tracer->recordException($span, $e);

            Log::channel('myxn')->error('Presale purchase failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            $this->tracer->endSpan($span);
        }
    }

    /**
     * Validate presale purchase against limits.
     *
     * @param User $user
     * @param float $usdAmount
     * @throws \Exception
     */
    protected function validatePresalePurchase(User $user, float $usdAmount): void
    {
        $config = $this->programs['presale'];

        // Check minimum purchase
        if ($usdAmount < $config['min_purchase_usd']) {
            throw new \Exception("Minimum purchase is \${$config['min_purchase_usd']} USD");
        }

        // Check maximum per wallet
        $existingPurchases = ProgramParticipation::where('user_id', $user->id)
            ->where('program_type', 'presale')
            ->whereIn('status', ['pending', 'completed'])
            ->sum('usd_amount');

        if (($existingPurchases + $usdAmount) > $config['max_per_wallet_usd']) {
            $remaining = $config['max_per_wallet_usd'] - $existingPurchases;
            throw new \Exception("Maximum purchase limit exceeded. You can purchase up to \${$remaining} USD more.");
        }
    }

    /**
     * Get presale statistics.
     *
     * @return array
     */
    public function getPresaleStats(): array
    {
        return Cache::remember('myxn_presale_stats', 300, function () {
            $participations = ProgramParticipation::where('program_type', 'presale')
                ->whereIn('status', ['pending', 'completed']);

            return [
                'total_raised_usd' => $participations->sum('usd_amount'),
                'total_tokens_sold' => $participations->sum('token_amount'),
                'total_participants' => $participations->distinct('user_id')->count(),
                'price_usd' => $this->programs['presale']['price_usd'],
                'is_active' => $this->programs['presale']['enabled'],
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Staking Program
    |--------------------------------------------------------------------------
    */

    /**
     * Stake MYXN tokens.
     *
     * @param User $user
     * @param float $amount
     * @return array
     */
    public function stakeTokens(User $user, float $amount): array
    {
        $span = $this->tracer->startSpan('myxn.staking.stake', [
            'user_id' => $user->id,
            'amount' => $amount,
        ]);

        try {
            if (!$this->programs['staking']['enabled']) {
                throw new \Exception('Staking is currently not active');
            }

            if ($amount < $this->programs['staking']['min_stake_amount']) {
                throw new \Exception("Minimum stake amount is {$this->programs['staking']['min_stake_amount']} MYXN");
            }

            $lockPeriodDays = $this->programs['staking']['lock_period_days'];
            $unlockDate = now()->addDays($lockPeriodDays);

            return DB::transaction(function () use ($user, $amount, $unlockDate, $span) {
                $participation = ProgramParticipation::create([
                    'user_id' => $user->id,
                    'program_type' => 'staking',
                    'token_amount' => $amount,
                    'status' => 'active',
                    'metadata' => [
                        'staked_at' => now()->toIso8601String(),
                        'unlock_date' => $unlockDate->toIso8601String(),
                        'apy' => $this->programs['staking']['apy_percentage'],
                    ],
                ]);

                $this->tracer->addEvent($span, 'stake_created', [
                    'participation_id' => $participation->id,
                ]);

                Log::channel('myxn')->info('Tokens staked', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'unlock_date' => $unlockDate->toIso8601String(),
                ]);

                return [
                    'success' => true,
                    'participation_id' => $participation->id,
                    'amount' => $amount,
                    'apy' => $this->programs['staking']['apy_percentage'],
                    'unlock_date' => $unlockDate->toIso8601String(),
                ];
            });
        } catch (\Exception $e) {
            $this->tracer->recordException($span, $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            $this->tracer->endSpan($span);
        }
    }

    /**
     * Get user's staking positions.
     *
     * @param User $user
     * @return array
     */
    public function getUserStakingPositions(User $user): array
    {
        $positions = ProgramParticipation::where('user_id', $user->id)
            ->where('program_type', 'staking')
            ->where('status', 'active')
            ->get();

        return $positions->map(function ($position) {
            $stakedAt = $position->metadata['staked_at'] ?? now()->toIso8601String();
            $unlockDate = $position->metadata['unlock_date'] ?? now()->toIso8601String();
            $apy = $position->metadata['apy'] ?? $this->programs['staking']['apy_percentage'];

            // Calculate earned rewards
            $daysStaked = now()->diffInDays($stakedAt);
            $dailyRate = $apy / 365 / 100;
            $earnedRewards = $position->token_amount * $dailyRate * $daysStaked;

            return [
                'id' => $position->id,
                'amount' => $position->token_amount,
                'staked_at' => $stakedAt,
                'unlock_date' => $unlockDate,
                'apy' => $apy,
                'earned_rewards' => round($earnedRewards, 4),
                'can_unstake' => now()->gte($unlockDate),
            ];
        })->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | Rewards Program
    |--------------------------------------------------------------------------
    */

    /**
     * Process referral reward.
     *
     * @param User $referrer
     * @param User $referee
     * @param float $purchaseAmount
     * @return array
     */
    public function processReferralReward(User $referrer, User $referee, float $purchaseAmount): array
    {
        $span = $this->tracer->startSpan('myxn.rewards.referral', [
            'referrer_id' => $referrer->id,
            'referee_id' => $referee->id,
        ]);

        try {
            if (!$this->programs['rewards']['enabled']) {
                throw new \Exception('Rewards program is not active');
            }

            $rewardPercentage = $this->programs['rewards']['referral_percentage'];
            $rewardAmount = ($purchaseAmount * $rewardPercentage) / 100;

            return DB::transaction(function () use ($referrer, $referee, $rewardAmount, $span) {
                $participation = ProgramParticipation::create([
                    'user_id' => $referrer->id,
                    'program_type' => 'referral_reward',
                    'token_amount' => $rewardAmount,
                    'status' => 'pending',
                    'metadata' => [
                        'referee_id' => $referee->id,
                        'reward_type' => 'referral',
                    ],
                ]);

                $this->tracer->addEvent($span, 'referral_reward_created', [
                    'reward_amount' => $rewardAmount,
                ]);

                Log::channel('myxn')->info('Referral reward created', [
                    'referrer_id' => $referrer->id,
                    'referee_id' => $referee->id,
                    'reward_amount' => $rewardAmount,
                ]);

                return [
                    'success' => true,
                    'reward_amount' => $rewardAmount,
                ];
            });
        } catch (\Exception $e) {
            $this->tracer->recordException($span, $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            $this->tracer->endSpan($span);
        }
    }

    /**
     * Process signup bonus.
     *
     * @param User $user
     * @return array
     */
    public function processSignupBonus(User $user): array
    {
        $span = $this->tracer->startSpan('myxn.rewards.signup_bonus', [
            'user_id' => $user->id,
        ]);

        try {
            if (!$this->programs['rewards']['enabled']) {
                throw new \Exception('Rewards program is not active');
            }

            // Check if user already received signup bonus
            $existingBonus = ProgramParticipation::where('user_id', $user->id)
                ->where('program_type', 'signup_bonus')
                ->exists();

            if ($existingBonus) {
                throw new \Exception('Signup bonus already claimed');
            }

            $bonusAmount = $this->programs['rewards']['signup_bonus'];

            return DB::transaction(function () use ($user, $bonusAmount, $span) {
                $participation = ProgramParticipation::create([
                    'user_id' => $user->id,
                    'program_type' => 'signup_bonus',
                    'token_amount' => $bonusAmount,
                    'status' => 'pending',
                    'metadata' => [
                        'claimed_at' => now()->toIso8601String(),
                    ],
                ]);

                $this->tracer->addEvent($span, 'signup_bonus_created', [
                    'bonus_amount' => $bonusAmount,
                ]);

                return [
                    'success' => true,
                    'bonus_amount' => $bonusAmount,
                ];
            });
        } catch (\Exception $e) {
            $this->tracer->recordException($span, $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            $this->tracer->endSpan($span);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Burn Program
    |--------------------------------------------------------------------------
    */

    /**
     * Execute quarterly burn.
     *
     * @param string $sourceTokenAccount
     * @return array
     */
    public function executeQuarterlyBurn(string $sourceTokenAccount): array
    {
        $span = $this->tracer->startSpan('myxn.burn.quarterly');

        try {
            if (!$this->programs['burn']['enabled']) {
                throw new \Exception('Burn program is not active');
            }

            $burnPercentage = $this->programs['burn']['quarterly_burn_percentage'];
            // This would need to fetch actual circulating supply
            // For now, using a placeholder calculation

            $result = $this->tokenService->burn(
                $sourceTokenAccount,
                0, // Amount would be calculated from supply
                'quarterly_burn_q' . ceil(now()->month / 3) . '_' . now()->year
            );

            return $result;
        } catch (\Exception $e) {
            $this->tracer->recordException($span, $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            $this->tracer->endSpan($span);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Program Information
    |--------------------------------------------------------------------------
    */

    /**
     * Get all program configurations for API response.
     *
     * @return array
     */
    public function getProgramsInfo(): array
    {
        return [
            'presale' => [
                'enabled' => $this->programs['presale']['enabled'],
                'price_usd' => $this->programs['presale']['price_usd'],
                'min_purchase_usd' => $this->programs['presale']['min_purchase_usd'],
                'max_per_wallet_usd' => $this->programs['presale']['max_per_wallet_usd'],
            ],
            'staking' => [
                'enabled' => $this->programs['staking']['enabled'],
                'apy' => $this->programs['staking']['apy_percentage'],
                'min_stake' => $this->programs['staking']['min_stake_amount'],
                'lock_period_days' => $this->programs['staking']['lock_period_days'],
            ],
            'rewards' => [
                'enabled' => $this->programs['rewards']['enabled'],
                'referral_percentage' => $this->programs['rewards']['referral_percentage'],
                'signup_bonus' => $this->programs['rewards']['signup_bonus'],
            ],
            'burn' => [
                'enabled' => $this->programs['burn']['enabled'],
                'quarterly_percentage' => $this->programs['burn']['quarterly_burn_percentage'],
            ],
        ];
    }
}
