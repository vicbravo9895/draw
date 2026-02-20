<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario con permisos para crear inspecciones (supervisor: crear, editar, completar)
        $creador = User::firstOrCreate(
            ['email' => 'creador@pluss.com'],
            [
                'name' => 'Usuario Creador',
                'password' => Hash::make('password'),
                'company_id' => null,
                'employee_number' => 'EMP-CRE',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $creador->syncRoles(['supervisor_calidad']);
        $creador->companies()->sync([]);

        // Usuario con permisos para realizar inspecciones (inspector: ver, crear, editar; sin completar)
        $realizador = User::firstOrCreate(
            ['email' => 'realizador@pluss.com'],
            [
                'name' => 'Usuario Realizador',
                'password' => Hash::make('password'),
                'company_id' => null,
                'employee_number' => 'EMP-REA',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $realizador->syncRoles(['inspector']);
        $realizador->companies()->sync([]);
    }
}
