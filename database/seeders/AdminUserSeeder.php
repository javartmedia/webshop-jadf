<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $adminRole = Role::where('slug', 'admin')->first();

        // Super Admin
        User::updateOrCreate(
            ['email' => 'superadmin@wenshop.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'role_id' => $superAdminRole->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // Admin
        User::updateOrCreate(
            ['email' => 'admin@wenshop.com'],
            [
                'name' => 'Admin Wenshop',
                'password' => Hash::make('password123'),
                'role_id' => $adminRole->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}
