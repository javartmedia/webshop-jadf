<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Full access to all features'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrative access'],
            ['name' => 'Staff', 'slug' => 'staff', 'description' => 'Limited admin access for order processing'],
            ['name' => 'Customer', 'slug' => 'customer', 'description' => 'Regular customer account'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
