<?php

namespace Database\Seeders;

use App\Models\FinancialProgram;
use Illuminate\Database\Seeder;

class FinancialProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $programs = [
            [
                'name' => 'MYXN Staking Pool',
                'slug' => 'myxn-staking',
                'type' => 'staking',
                'description' => 'Stake your MYXN tokens and earn up to 10% APY. Minimum lock period of 30 days with early withdrawal penalty.',
                'apy_rate' => 10.00,
                'min_amount' => 100,
                'max_amount' => 1000000,
                'lock_period_days' => 30,
                'early_withdrawal_penalty' => 5,
                'total_pool_limit' => 50000000,
                'reward_frequency' => 'daily',
                'compound_enabled' => true,
                'status' => 'active',
                'is_featured' => true,
                'starts_at' => now(),
                'meta' => [
                    'tier' => 'standard',
                    'bonus_multiplier' => 1.0,
                ],
            ],
            [
                'name' => 'MYXN Premium Staking',
                'slug' => 'myxn-premium-staking',
                'type' => 'staking',
                'description' => 'Premium staking with higher APY for long-term holders. 90-day lock period with 15% APY.',
                'apy_rate' => 15.00,
                'min_amount' => 10000,
                'max_amount' => 5000000,
                'lock_period_days' => 90,
                'early_withdrawal_penalty' => 10,
                'total_pool_limit' => 20000000,
                'reward_frequency' => 'weekly',
                'compound_enabled' => true,
                'status' => 'active',
                'is_featured' => true,
                'starts_at' => now(),
                'meta' => [
                    'tier' => 'premium',
                    'bonus_multiplier' => 1.5,
                ],
            ],
            [
                'name' => 'MYXN Lending Pool',
                'slug' => 'myxn-lending',
                'type' => 'lending',
                'description' => 'Provide liquidity to the MYXN lending pool and earn 8% APY. Flexible withdrawal with no lock period.',
                'apy_rate' => 8.00,
                'min_amount' => 500,
                'max_amount' => 2000000,
                'lock_period_days' => 0,
                'early_withdrawal_penalty' => 0,
                'total_pool_limit' => 30000000,
                'reward_frequency' => 'daily',
                'compound_enabled' => false,
                'status' => 'active',
                'is_featured' => false,
                'starts_at' => now(),
                'meta' => [
                    'utilization_rate' => 0.75,
                    'collateral_factor' => 0.8,
                ],
            ],
            [
                'name' => 'MYXN Savings Account',
                'slug' => 'myxn-savings',
                'type' => 'savings',
                'description' => 'Low-risk savings account with 5% APY. Perfect for beginners with flexible deposits and withdrawals.',
                'apy_rate' => 5.00,
                'min_amount' => 50,
                'max_amount' => 500000,
                'lock_period_days' => 0,
                'early_withdrawal_penalty' => 0,
                'total_pool_limit' => 100000000,
                'reward_frequency' => 'daily',
                'compound_enabled' => true,
                'status' => 'active',
                'is_featured' => false,
                'starts_at' => now(),
                'meta' => [
                    'insurance_covered' => true,
                    'max_daily_withdrawal' => 10000,
                ],
            ],
            [
                'name' => 'MYXN Community Rewards',
                'slug' => 'myxn-rewards',
                'type' => 'rewards',
                'description' => 'Earn rewards for participating in the MYXN ecosystem. Complete tasks and earn bonus tokens.',
                'apy_rate' => 0,
                'min_amount' => 10,
                'max_amount' => null,
                'lock_period_days' => 0,
                'early_withdrawal_penalty' => 0,
                'total_pool_limit' => null,
                'reward_frequency' => 'on_maturity',
                'compound_enabled' => false,
                'status' => 'active',
                'is_featured' => false,
                'starts_at' => now(),
                'meta' => [
                    'task_categories' => ['social', 'referral', 'trading', 'governance'],
                    'max_daily_tasks' => 10,
                ],
            ],
        ];

        foreach ($programs as $program) {
            FinancialProgram::updateOrCreate(
                ['slug' => $program['slug']],
                $program
            );
        }

        $this->command->info('Financial programs seeded successfully!');
    }
}
