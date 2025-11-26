<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@myxenpay.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'kyc_level' => 3,
            'email_verified_at' => now(),
        ]);

        Wallet::create([
            'user_id' => $admin->id,
            'balance' => 1000,
            'myxn_balance' => 100000,
            'status' => 'active',
        ]);

        // Create test merchant
        $merchantUser = User::create([
            'name' => 'Test Merchant',
            'email' => 'merchant@myxenpay.com',
            'password' => Hash::make('password'),
            'role' => 'merchant',
            'status' => 'active',
            'kyc_level' => 2,
            'email_verified_at' => now(),
        ]);

        $merchantWallet = Wallet::create([
            'user_id' => $merchantUser->id,
            'solana_address' => '5yKHV8H9n1XeyGPxJ9TF7HnNY6W5YvPBjcPvQrSxLjK1',
            'balance' => 500,
            'myxn_balance' => 50000,
            'status' => 'active',
        ]);

        Merchant::create([
            'user_id' => $merchantUser->id,
            'business_name' => 'Test Coffee Shop',
            'business_type' => 'retail',
            'wallet_address' => $merchantWallet->solana_address,
            'status' => 'active',
            'commission_rate' => 0.50,
            'description' => 'A test coffee shop for demo purposes',
        ]);

        // Create test user
        $testUser = User::create([
            'name' => 'Test User',
            'email' => 'user@myxenpay.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => 'active',
            'kyc_level' => 1,
            'email_verified_at' => now(),
        ]);

        Wallet::create([
            'user_id' => $testUser->id,
            'solana_address' => '3yKHV8H9n1XeyGPxJ9TF7HnNY6W5YvPBjcPvQrSxLjK2',
            'balance' => 100,
            'myxn_balance' => 10000,
            'status' => 'active',
        ]);
    }
}
