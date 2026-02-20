<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder mínimo para producción: roles + un usuario sin company (inspector + supervisor).
 * Credenciales: demo@pluss.com / password (o usar env APP_DEMO_*).
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $user = User::firstOrCreate(
            ['email' => env('APP_DEMO_EMAIL', 'demo@pluss.com')],
            [
                'name' => env('APP_DEMO_NAME', 'Usuario Demo'),
                'password' => Hash::make(env('APP_DEMO_PASSWORD', 'password')),
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
