<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // WARNING: These are default development passwords.
        // NEVER use in production without changing credentials!
        // TODO: In production, use secure password generation or environment variables
        $defaultPassword = app()->environment('production')
            ? throw new \RuntimeException('Cannot seed default passwords in production. Use secure credentials.')
            : 'password';

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@myxenpay.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');

        // Create admin wallet
        Wallet::firstOrCreate(
            ['user_id' => $admin->id, 'currency' => 'MYXN'],
            [
                'address' => 'ADMIN_' . Str::random(32),
                'public_key' => base64_encode(random_bytes(32)),
                'balance' => 1000000,
                'is_primary' => true,
            ]
        );

        // Create sample merchant
        $merchant = User::firstOrCreate(
            ['email' => 'merchant@example.com'],
            [
                'name' => 'Sample Merchant',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
            ]
        );
        $merchant->assignRole('merchant');
        $merchant->assignRole('user');

        Wallet::firstOrCreate(
            ['user_id' => $merchant->id, 'currency' => 'MYXN'],
            [
                'address' => 'MERCHANT_' . Str::random(32),
                'public_key' => base64_encode(random_bytes(32)),
                'balance' => 10000,
                'is_primary' => true,
            ]
        );

        // Create sample university
        $university = User::firstOrCreate(
            ['email' => 'university@example.edu'],
            [
                'name' => 'Sample University',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
            ]
        );
        $university->assignRole('university');
        $university->assignRole('user');

        Wallet::firstOrCreate(
            ['user_id' => $university->id, 'currency' => 'MYXN'],
            [
                'address' => 'UNIVERSITY_' . Str::random(32),
                'public_key' => base64_encode(random_bytes(32)),
                'balance' => 50000,
                'is_primary' => true,
            ]
        );

        // Create sample regular user
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Sample User',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
            ]
        );
        $user->assignRole('user');

        Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => 'MYXN'],
            [
                'address' => 'USER_' . Str::random(32),
                'public_key' => base64_encode(random_bytes(32)),
                'balance' => 100,
                'is_primary' => true,
            ]
        );
    }
}
