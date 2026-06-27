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
        $adminRole = Role::where('slug', 'administrador')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@fathermotosport.com'],
            [
                'role_id' => $adminRole->id,
                'first_name' => 'Admin',
                'last_name' => 'FatherMotoSport',
                'phone' => '+59168736384',
                'password' => Hash::make('Admin123!'),
                'status' => 'active',
                'email_verified_at' => now(),
                'loyalty_discount_used' => false,
            ]
        );
    }
}
