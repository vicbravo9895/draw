<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::firstOrCreate(
            ['public_code' => 'ACME-001'],
            [
                'name' => 'ACME Manufacturing',
                'status' => 'active',
                'timezone' => 'America/Mexico_City',
                'contact_email' => 'contacto@acme-mfg.com',
                'allowed_domains' => ['acme-mfg.com'],
                'allowed_emails' => ['viewer@acme-mfg.com', 'quality@acme-mfg.com'],
                'allow_exports' => true,
            ]
        );

        Company::firstOrCreate(
            ['public_code' => 'BETA-002'],
            [
                'name' => 'Beta Industries',
                'status' => 'active',
                'timezone' => 'America/Mexico_City',
                'contact_email' => 'info@beta-ind.com',
                'allowed_domains' => ['beta-ind.com'],
                'allowed_emails' => ['viewer@beta-ind.com'],
                'allow_exports' => true,
            ]
        );
    }
}
