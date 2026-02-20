<?php

namespace App\Listeners;

use App\Events\InspectionCompleted;
use App\Events\InspectionUpdated;
use App\Events\QualityAlertTriggered;
use App\Services\QualityThresholdService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CheckQualityThresholds implements ShouldQueue
{
    public function handleInspectionUpdated(InspectionUpdated $event): void
    {
        $this->checkThresholds($event->inspection);
    }

    public function handleInspectionCompleted(InspectionCompleted $event): void
    {
        $this->checkThresholds($event->inspection);
    }

    /**
     * Subscribe to multiple events.
     */
    public function subscribe($events): array
    {
        return [
            InspectionUpdated::class => 'handleInspectionUpdated',
            InspectionCompleted::class => 'handleInspectionCompleted',
        ];
    }

    protected function checkThresholds($inspection): void
    {
        $inspection->load('parts.items');

        foreach ($inspection->parts as $part) {
            $total = $part->part_total;
            $defects = $part->part_total_defects;

            if ($total === 0) {
                continue;
            }

            $defectRate = round(($defects / $total) * 100, 1);
            $ppm = (int) round(($defects / $total) * 1_000_000);

            $severity = QualityThresholdService::severityForDefectRate($defectRate)
                ?? QualityThresholdService::severityForPpm($ppm);

            if ($severity) {
                QualityAlertTriggered::dispatch($inspection->company_id, [
                    'severity' => $severity,
                    'type' => 'part',
                    'identifier' => $part->part_number,
                    'defect_rate' => $defectRate,
                    'ppm' => $ppm,
                    'defects' => $defects,
                    'total' => $total,
                    'inspection_id' => $inspection->id,
                    'reference_code' => $inspection->reference_code,
                    'message' => "Parte {$part->part_number} — Tasa de Defectos: {$defectRate}%",
                    'recommended_actions' => QualityThresholdService::recommendedActions($severity, 'part'),
                    'timestamp' => now()->toISOString(),
                ]);
            }
        }
    }
}
