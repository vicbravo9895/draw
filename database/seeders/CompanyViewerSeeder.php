<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyViewer;
use Illuminate\Database\Seeder;

/**
 * Companies consult the system only through the portal (real-time panel),
 * via the link and their account. These are the viewers that represent "la empresa consultando".
 */
class CompanyViewerSeeder extends Seeder
{
    public function run(): void
    {
        $acme = Company::where('public_code', 'ACME-001')->first();
        $beta = Company::where('public_code', 'BETA-002')->first();

        if ($acme) {
            CompanyViewer::firstOrCreate(
                ['company_id' => $acme->id, 'email' => 'viewer@acme-mfg.com'],
                ['name' => 'Viewer ACME']
            );
            CompanyViewer::firstOrCreate(
                ['company_id' => $acme->id, 'email' => 'quality@acme-mfg.com'],
                ['name' => 'Quality ACME']
            );
            CompanyViewer::firstOrCreate(
                ['company_id' => $acme->id, 'email' => 'contacto@acme-mfg.com'],
                ['name' => 'Contacto ACME']
            );
        }

        if ($beta) {
            CompanyViewer::firstOrCreate(
                ['company_id' => $beta->id, 'email' => 'viewer@beta-ind.com'],
                ['name' => 'Viewer Beta']
            );
        }
    }
}
