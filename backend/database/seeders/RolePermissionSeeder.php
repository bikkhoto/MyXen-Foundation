<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = [
            ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Full system access'],
            ['name' => 'merchant', 'display_name' => 'Merchant', 'description' => 'Merchant account with payment processing'],
            ['name' => 'user', 'display_name' => 'User', 'description' => 'Standard user account'],
            ['name' => 'university', 'display_name' => 'University', 'description' => 'University/educational institution'],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(['name' => $roleData['name']], $roleData);
        }

        // Create permissions
        $permissions = [
            ['name' => 'manage_users', 'display_name' => 'Manage Users', 'description' => 'Create, update, delete users'],
            ['name' => 'manage_wallets', 'display_name' => 'Manage Wallets', 'description' => 'View and manage all wallets'],
            ['name' => 'manage_transactions', 'display_name' => 'Manage Transactions', 'description' => 'View and manage all transactions'],
            ['name' => 'manage_merchants', 'display_name' => 'Manage Merchants', 'description' => 'Approve and manage merchants'],
            ['name' => 'view_reports', 'display_name' => 'View Reports', 'description' => 'Access analytics and reports'],
            ['name' => 'process_payments', 'display_name' => 'Process Payments', 'description' => 'Accept and process payments'],
            ['name' => 'manage_students', 'display_name' => 'Manage Students', 'description' => 'Manage student accounts'],
        ];

        foreach ($permissions as $permData) {
            Permission::firstOrCreate(['name' => $permData['name']], $permData);
        }

        // Assign permissions to roles
        $adminRole = Role::where('name', 'admin')->first();
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));

        $merchantRole = Role::where('name', 'merchant')->first();
        $merchantRole->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', ['process_payments', 'view_reports'])->pluck('id')
        );

        $universityRole = Role::where('name', 'university')->first();
        $universityRole->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', ['manage_students', 'view_reports'])->pluck('id')
        );
    }
}
