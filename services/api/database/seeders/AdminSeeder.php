<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates a default superadmin account for initial system access.
     */
    public function run(): void
    {
        // Check if superadmin already exists
        if (Admin::where('email', 'admin@myxen.com')->exists()) {
            $this->command->info('Superadmin already exists. Skipping...');
            return;
        }

        // Create default superadmin
        Admin::create([
            'name' => 'MyXen SuperAdmin',
            'email' => 'admin@myxen.com',
            'password' => 'password', // Will be hashed by model mutator
            'role' => Admin::ROLE_SUPERADMIN,
        ]);

        $this->command->info('Default superadmin created successfully!');
        $this->command->info('Email: admin@myxen.com');
        $this->command->info('Password: password');
        $this->command->warn('⚠️  Please change the password after first login!');
    }
}
