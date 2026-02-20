<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Backoffice users are system employees only; they do not "belong" to a company.
 * They are assigned to companies they work with via user_companies pivot.
 * Companies consult only via the portal (CompanyViewer) — see CompanyViewerSeeder.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $acme = Company::where('public_code', 'ACME-001')->first();
        $beta = Company::where('public_code', 'BETA-002')->first();

        // Super Admin — system only, no company assignment
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@pluss.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'company_id' => null,
                'employee_number' => 'EMP-000',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole('super_admin');
        $superAdmin->companies()->sync([]);

        // Company Admin — employee assigned to work with ACME
        $companyAdmin = User::firstOrCreate(
            ['email' => 'admin@acme-mfg.com'],
            [
                'name' => 'ACME Admin',
                'password' => Hash::make('password'),
                'company_id' => null,
                'employee_number' => 'EMP-001',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $companyAdmin->assignRole('company_admin');
        $companyAdmin->companies()->sync($acme ? [$acme->id] : []);

        // Supervisor — employee assigned to ACME
        $supervisor = User::firstOrCreate(
            ['email' => 'supervisor@acme-mfg.com'],
            [
                'name' => 'Carlos Supervisor',
                'password' => Hash::make('password'),
                'company_id' => null,
                'employee_number' => 'EMP-002',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $supervisor->assignRole('supervisor_calidad');
        $supervisor->companies()->sync($acme ? [$acme->id] : []);

        // Inspector — employee assigned to ACME
        $inspector = User::firstOrCreate(
            ['email' => 'inspector@acme-mfg.com'],
            [
                'name' => 'Maria Inspector',
                'password' => Hash::make('password'),
                'company_id' => null,
                'employee_number' => 'EMP-003',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $inspector->assignRole('inspector');
        $inspector->companies()->sync($acme ? [$acme->id] : []);

        // Auditor — employee assigned to ACME
        $auditor = User::firstOrCreate(
            ['email' => 'auditor@acme-mfg.com'],
            [
                'name' => 'Luis Auditor',
                'password' => Hash::make('password'),
                'company_id' => null,
                'employee_number' => 'EMP-004',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $auditor->assignRole('auditor_interno');
        $auditor->companies()->sync($acme ? [$acme->id] : []);

        // Beta Company Admin — employee assigned to Beta
        if ($beta) {
            $betaAdmin = User::firstOrCreate(
                ['email' => 'admin@beta-ind.com'],
                [
                    'name' => 'Beta Admin',
                    'password' => Hash::make('password'),
                    'company_id' => null,
                    'employee_number' => 'BEMP-001',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $betaAdmin->assignRole('company_admin');
            $betaAdmin->companies()->sync([$beta->id]);
        }
    }
}
