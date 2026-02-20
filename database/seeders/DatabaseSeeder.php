<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Incluye un escenario para probar WebSockets: inspector (backoffice) + empresa (portal).
     * Ver docs/WEBSOCKET_DEMO.md para los pasos.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            CompanyViewerSeeder::class,
            InspectionSeeder::class,
        ]);
    }
}
