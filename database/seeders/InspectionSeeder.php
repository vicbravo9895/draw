<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\InspectionPart;
use App\Models\User;
use Illuminate\Database\Seeder;

class InspectionSeeder extends Seeder
{
    public function run(): void
    {
        $acme = Company::where('public_code', 'ACME-001')->first();
        $beta = Company::where('public_code', 'BETA-002')->first();
        $inspector = User::where('email', 'inspector@acme-mfg.com')->first();
        $supervisor = User::where('email', 'supervisor@acme-mfg.com')->first();

        if (! $acme || ! $inspector || ! $supervisor) {
            return;
        }

        // ----- Escenario demo WebSocket: 1 inspección "en progreso" para probar en vivo -----
        // Inspector (backoffice) edita esta inspección; la empresa (portal viewer) ve el cambio al instante.
        $demoInspection = Inspection::firstOrCreate(
            [
                'company_id' => $acme->id,
                'reference_code' => 'INS-DEMO-WS-001',
            ],
            [
                'date' => now()->format('Y-m-d'),
                'shift' => '1st',
                'project' => 'Demo WebSocket',
                'area_line' => 'Línea Demo',
                'scheduled_by' => $supervisor->id,
                'assigned_inspector_id' => $inspector->id,
                'start_time' => '08:00',
                'end_time' => null,
                'comment_general' => 'Inspección de prueba para ver actualizaciones en tiempo real en el portal.',
                'status' => 'in_progress',
            ]
        );
        $demoInspection->inspectors()->syncWithoutDetaching([$inspector->id]);

        if ($demoInspection->wasRecentlyCreated) {
            $part = InspectionPart::firstOrCreate(
                ['inspection_id' => $demoInspection->id, 'part_number' => 'PN-DEMO-001'],
                ['company_id' => $acme->id, 'order' => 1, 'comment_part' => 'Parte demo']
            );
            InspectionItem::firstOrCreate(
                [
                    'inspection_part_id' => $part->id,
                    'serial_number' => 'SN-DEMO-001',
                ],
                [
                    'company_id' => $acme->id,
                    'lot_date' => now()->format('Y-m-d'),
                    'good_qty' => 100,
                    'defects_qty' => 2,
                ]
            );
        }

        // Create ACME inspections
        foreach (range(1, 10) as $i) {
            $date = now()->subDays(rand(0, 30));
            $status = ['pending', 'in_progress', 'completed', 'completed', 'completed'][rand(0, 4)];

            $inspection = Inspection::create([
                'company_id' => $acme->id,
                'date' => $date->format('Y-m-d'),
                'shift' => ['1st', '2nd', '3rd'][rand(0, 2)],
                'project' => ['Proyecto Alpha', 'Proyecto Beta', 'Proyecto Gamma'][rand(0, 2)],
                'area_line' => ['Linea ' . rand(1, 5), 'Area ' . chr(65 + rand(0, 4))][rand(0, 1)],
                'scheduled_by' => $supervisor->id,
                'assigned_inspector_id' => $inspector->id,
                'start_time' => '08:00',
                'end_time' => $status === 'completed' ? '16:00' : null,
                'comment_general' => $status === 'completed' ? 'Inspección completada sin novedades.' : null,
                'status' => $status,
                'reference_code' => sprintf('INS-%s-%04d', $date->format('Ymd'), $i),
            ]);

            // Create 1-3 parts per inspection
            foreach (range(1, rand(1, 3)) as $j) {
                $part = InspectionPart::create([
                    'company_id' => $acme->id,
                    'inspection_id' => $inspection->id,
                    'part_number' => 'PN-' . str_pad(rand(1000, 9999), 4, '0'),
                    'comment_part' => $j === 1 ? 'Parte principal' : null,
                    'order' => $j,
                ]);

                // Create 3-8 items per part
                foreach (range(1, rand(3, 8)) as $k) {
                    $goodQty = rand(50, 500);
                    $defectsQty = rand(0, (int) ($goodQty * 0.1));

                    InspectionItem::create([
                        'company_id' => $acme->id,
                        'inspection_part_id' => $part->id,
                        'serial_number' => 'SN-' . str_pad(rand(10000, 99999), 5, '0'),
                        'lot_date' => $date->copy()->subDays(rand(0, 5))->format('Y-m-d'),
                        'good_qty' => $goodQty,
                        'defects_qty' => $defectsQty,
                    ]);
                }
            }
        }

        // Create a couple of Beta inspections (to test isolation)
        $betaAdmin = User::where('email', 'admin@beta-ind.com')->first();
        if ($beta && $betaAdmin) {
            foreach (range(1, 3) as $i) {
                $date = now()->subDays(rand(0, 15));

                $inspection = Inspection::create([
                    'company_id' => $beta->id,
                    'date' => $date->format('Y-m-d'),
                    'shift' => '1st',
                    'project' => 'Proyecto Delta',
                    'area_line' => 'Linea 1',
                    'scheduled_by' => $betaAdmin->id,
                    'assigned_inspector_id' => null,
                    'start_time' => '07:00',
                    'end_time' => '15:00',
                    'status' => 'completed',
                    'reference_code' => sprintf('INS-%s-%04d', $date->format('Ymd'), 100 + $i),
                ]);

                $part = InspectionPart::create([
                    'company_id' => $beta->id,
                    'inspection_id' => $inspection->id,
                    'part_number' => 'BPN-' . rand(1000, 9999),
                    'order' => 1,
                ]);

                foreach (range(1, 5) as $k) {
                    $goodQty = rand(100, 300);
                    $def = rand(0, 10);
                    InspectionItem::create([
                        'company_id' => $beta->id,
                        'inspection_part_id' => $part->id,
                        'serial_number' => 'BSN-' . rand(10000, 99999),
                        'lot_date' => $date->format('Y-m-d'),
                        'good_qty' => $goodQty,
                        'defects_qty' => $def,
                    ]);
                }
            }
        }
    }
}
