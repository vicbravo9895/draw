<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de producción: crea roles, una empresa por defecto y dos usuarios listos para usar.
 * - Usuario inspector (rol: inspector)
 * - Usuario admin/supervisor (rol: supervisor_calidad)
 *
 * Credenciales por defecto (cambiar en producción vía variables de entorno):
 * - Inspector: inspector@pluss.com / password
 * - Admin:    admin@pluss.com / password
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $company = Company::firstOrCreate(
            ['public_code' => 'PLUSS-001'],
            [
                'name' => 'Empresa por defecto',
                'status' => 'active',
                'timezone' => 'America/Mexico_City',
                'contact_email' => 'contacto@pluss.com',
                'allowed_domains' => ['pluss.com'],
                'allowed_emails' => [],
                'allow_exports' => true,
            ]
        );

        $inspectorPassword = env('APP_INSPECTOR_PASSWORD', 'password');
        $adminPassword = env('APP_ADMIN_PASSWORD', 'password');

        $inspector = User::firstOrCreate(
            ['email' => env('APP_INSPECTOR_EMAIL', 'inspector@pluss.com')],
            [
                'name' => env('APP_INSPECTOR_NAME', 'Usuario Inspector'),
                'password' => Hash::make($inspectorPassword),
                'company_id' => null,
                'employee_number' => 'INS-001',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $inspector->assignRole('inspector');
        $inspector->companies()->sync([$company->id]);

        $admin = User::firstOrCreate(
            ['email' => env('APP_ADMIN_EMAIL', 'admin@pluss.com')],
            [
                'name' => env('APP_ADMIN_NAME', 'Usuario Admin'),
                'password' => Hash::make($adminPassword),
                'company_id' => null,
                'employee_number' => 'ADM-001',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('supervisor_calidad');
        $admin->companies()->sync([$company->id]);
    }
}
