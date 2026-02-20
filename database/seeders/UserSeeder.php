<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@pluss.com'],
            [
                'name' => 'Usuario Demo',
                'password' => Hash::make('password'),
                'company_id' => null,
                'employee_number' => 'EMP-001',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $user->syncRoles(['inspector', 'supervisor_calidad']);
        $user->companies()->sync([]);
    }
}
