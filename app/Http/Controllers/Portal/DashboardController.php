<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\QualityThresholdService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $companyId = auth('portal')->user()->company_id;

        $monthKpis = $this->getKpis($companyId, now()->startOfMonth(), now());
        $weekKpis = $this->getKpis($companyId, now()->startOfWeek(), now());
        $todayKpis = $this->getKpis($companyId, now()->startOfDay(), now());

        $qualityByPart = $this->getQualityByPart($companyId);
        $topOffenders = $this->getTopOffenders($companyId, $monthKpis['total_defects']);
        $trend = $this->getTrendData($companyId);
        $alerts = $this->deriveAlerts($companyId);
        $lotsAtRisk = $this->getLotsAtRisk($companyId);
        $recentInspections = $this->getRecentInspections($companyId);

        return Inertia::render('Portal/Dashboard', [
            'company' => auth('portal')->user()->company->only('name', 'public_code', 'logo_path'),
            'kpis' => [
                'month' => $monthKpis,
                'week' => $weekKpis,
                'today' => $todayKpis,
            ],
            'lotsAtRisk' => $lotsAtRisk,
            'qualityByPart' => $qualityByPart,
            'topOffenders' => $topOffenders,
            'trend' => $trend,
            'alerts' => $alerts,
            'recentInspections' => $recentInspections,
        ]);
    }

    /**
     * Core KPI computation for a given date range.
     */
    protected function getKpis(int $companyId, $from, $to): array
    {
        $result = DB::table('inspection_items')
            ->join('inspection_parts', 'inspection_items.inspection_part_id', '=', 'inspection_parts.id')
            ->join('inspections', 'inspection_parts.inspection_id', '=', 'inspections.id')
            ->where('inspections.company_id', $companyId)
            ->whereBetween('inspections.date', [$from, $to])
            ->whereNull('inspections.deleted_at')
            ->select(
                DB::raw('COALESCE(SUM(inspection_items.good_qty), 0) as good'),
                DB::raw('COALESCE(SUM(inspection_items.defects_qty), 0) as defects'),
                DB::raw('COUNT(DISTINCT inspections.id) as inspection_count')
            )
            ->first();

        $good = (int) $result->good;
        $defects = (int) $result->defects;
        $total = $good + $defects;

        $fpy = $total > 0 ? round(($good / $total) * 100, 1) : null;
        $ppm = $total > 0 ? (int) round(($defects / $total) * 1_000_000) : null;
        $defectRate = $total > 0 ? round(($defects / $total) * 100, 2) : 0;

        return [
            'total_inspected' => $total,
            'total_good' => $good,
            'total_defects' => $defects,
            'fpy' => $fpy,
            'ppm' => $ppm,
            'defect_rate' => $defectRate,
            'inspection_count' => (int) $result->inspection_count,
        ];
    }

    /**
     * Quality breakdown by part number for the heatmap.
     */
    protected function getQualityByPart(int $companyId): array
    {
        return DB::table('inspection_items')
            ->join('inspection_parts', 'inspection_items.inspection_part_id', '=', 'inspection_parts.id')
            ->join('inspections', 'inspection_parts.inspection_id', '=', 'inspections.id')
            ->where('inspections.company_id', $companyId)
            ->where('inspections.date', '>=', now()->startOfMonth())
            ->whereNull('inspections.deleted_at')
            ->select(
                'inspection_parts.part_number',
                DB::raw('SUM(inspection_items.good_qty) as total_good'),
                DB::raw('SUM(inspection_items.defects_qty) as total_defects'),
                DB::raw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) as total_inspected')
            )
            ->groupBy('inspection_parts.part_number')
            ->havingRaw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) > 0')
            ->orderByRaw('(SUM(inspection_items.defects_qty)::float / NULLIF(SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty), 0)) DESC')
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total_inspected;
                $defects = (int) $row->total_defects;
                $good = (int) $row->total_good;
                $quality = $total > 0 ? round(($good / $total) * 100, 1) : null;
                $ppm = $total > 0 ? (int) round(($defects / $total) * 1_000_000) : null;

                return [
                    'part_number' => $row->part_number,
                    'quality' => $quality,
                    'ppm' => $ppm,
                    'total_inspected' => $total,
                    'total_defects' => $defects,
                    'total_good' => $good,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Top offenders: worst part, lot, shift, and area/line.
     */
    protected function getTopOffenders(int $companyId, int $totalMonthDefects): array
    {
        $baseQuery = fn () => DB::table('inspection_items')
            ->join('inspection_parts', 'inspection_items.inspection_part_id', '=', 'inspection_parts.id')
            ->join('inspections', 'inspection_parts.inspection_id', '=', 'inspections.id')
            ->where('inspections.company_id', $companyId)
            ->where('inspections.date', '>=', now()->startOfMonth())
            ->whereNull('inspections.deleted_at');

        $worstPart = $baseQuery()
            ->select(
                'inspection_parts.part_number as identifier',
                DB::raw('SUM(inspection_items.defects_qty) as defects'),
                DB::raw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) as total')
            )
            ->groupBy('inspection_parts.part_number')
            ->orderByDesc('defects')
            ->first();

        $worstLot = $baseQuery()
            ->whereNotNull('inspection_items.lot_date')
            ->where('inspection_items.lot_date', '!=', '')
            ->select(
                'inspection_items.lot_date as identifier',
                DB::raw('SUM(inspection_items.defects_qty) as defects'),
                DB::raw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) as total')
            )
            ->groupBy('inspection_items.lot_date')
            ->orderByDesc('defects')
            ->first();

        $worstShift = $baseQuery()
            ->whereNotNull('inspections.shift')
            ->where('inspections.shift', '!=', '')
            ->select(
                'inspections.shift as identifier',
                DB::raw('SUM(inspection_items.defects_qty) as defects'),
                DB::raw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) as total')
            )
            ->groupBy('inspections.shift')
            ->orderByDesc('defects')
            ->first();

        $worstArea = $baseQuery()
            ->whereNotNull('inspections.area_line')
            ->where('inspections.area_line', '!=', '')
            ->select(
                'inspections.area_line as identifier',
                DB::raw('SUM(inspection_items.defects_qty) as defects'),
                DB::raw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) as total')
            )
            ->groupBy('inspections.area_line')
            ->orderByDesc('defects')
            ->first();

        $format = function ($row, string $type) use ($totalMonthDefects) {
            if (! $row || (int) $row->defects === 0) {
                return null;
            }
            $defects = (int) $row->defects;
            $total = (int) $row->total;
            $pctOfTotal = $totalMonthDefects > 0 ? round(($defects / $totalMonthDefects) * 100, 1) : 0;
            $defectRate = $total > 0 ? round(($defects / $total) * 100, 1) : 0;

            return [
                'type' => $type,
                'identifier' => $row->identifier,
                'defects' => $defects,
                'total' => $total,
                'defect_rate' => $defectRate,
                'pct_of_total' => $pctOfTotal,
            ];
        };

        return array_values(array_filter([
            $format($worstPart, 'part'),
            $format($worstLot, 'lot'),
            $format($worstShift, 'shift'),
            $format($worstArea, 'area'),
        ]));
    }

    /**
     * Daily quality trend for the last 30 days + direction indicator.
     */
    protected function getTrendData(int $companyId): array
    {
        $daily = DB::table('inspection_items')
            ->join('inspection_parts', 'inspection_items.inspection_part_id', '=', 'inspection_parts.id')
            ->join('inspections', 'inspection_parts.inspection_id', '=', 'inspections.id')
            ->where('inspections.company_id', $companyId)
            ->where('inspections.date', '>=', now()->subDays(30))
            ->whereNull('inspections.deleted_at')
            ->select(
                DB::raw('inspections.date as date'),
                DB::raw('SUM(inspection_items.good_qty) as good'),
                DB::raw('SUM(inspection_items.defects_qty) as defects')
            )
            ->groupBy('inspections.date')
            ->orderBy('inspections.date')
            ->get()
            ->map(function ($row) {
                $good = (int) $row->good;
                $defects = (int) $row->defects;
                $total = $good + $defects;
                $quality = $total > 0 ? round(($good / $total) * 100, 1) : null;

                return [
                    'date' => $row->date,
                    'quality' => $quality,
                    'total' => $total,
                    'good' => $good,
                    'defects' => $defects,
                ];
            })
            ->values()
            ->toArray();

        $direction = $this->computeDirection($daily);

        return [
            'daily' => $daily,
            'direction' => $direction,
        ];
    }

    /**
     * Simple direction: compare avg quality of last 7 days vs previous 7 days.
     */
    protected function computeDirection(array $daily): string
    {
        $qualityValues = array_filter(array_column($daily, 'quality'), fn ($v) => $v !== null);

        if (count($qualityValues) < 4) {
            return 'stable';
        }

        $half = (int) ceil(count($qualityValues) / 2);
        $firstHalf = array_slice($qualityValues, 0, $half);
        $secondHalf = array_slice($qualityValues, $half);

        $avgFirst = count($firstHalf) > 0 ? array_sum($firstHalf) / count($firstHalf) : 0;
        $avgSecond = count($secondHalf) > 0 ? array_sum($secondHalf) / count($secondHalf) : 0;

        $diff = $avgSecond - $avgFirst;

        if ($diff > 1.0) {
            return 'improving';
        }

        if ($diff < -1.0) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Derive alerts from current data: parts/lots with high defect rates.
     */
    protected function deriveAlerts(int $companyId): array
    {
        $thresholds = QualityThresholdService::defaults();
        $alerts = [];

        $parts = DB::table('inspection_items')
            ->join('inspection_parts', 'inspection_items.inspection_part_id', '=', 'inspection_parts.id')
            ->join('inspections', 'inspection_parts.inspection_id', '=', 'inspections.id')
            ->where('inspections.company_id', $companyId)
            ->where('inspections.date', '>=', now()->startOfMonth())
            ->whereNull('inspections.deleted_at')
            ->select(
                'inspection_parts.part_number',
                DB::raw('SUM(inspection_items.defects_qty) as defects'),
                DB::raw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) as total')
            )
            ->groupBy('inspection_parts.part_number')
            ->havingRaw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) > 0')
            ->get();

        foreach ($parts as $part) {
            $total = (int) $part->total;
            $defects = (int) $part->defects;
            $defectRate = $total > 0 ? round(($defects / $total) * 100, 1) : 0;
            $ppm = $total > 0 ? (int) round(($defects / $total) * 1_000_000) : 0;

            if ($defectRate > $thresholds['critical_defect_rate']) {
                $alerts[] = [
                    'severity' => 'critical',
                    'type' => 'part',
                    'identifier' => $part->part_number,
                    'defect_rate' => $defectRate,
                    'ppm' => $ppm,
                    'defects' => $defects,
                    'total' => $total,
                    'message' => "Parte {$part->part_number} tiene {$defectRate}% de tasa de defectos",
                    'recommended_actions' => [
                        'Contener producción inmediatamente',
                        'Inspeccionar lotes recientes',
                        'Revisar parámetros del proceso',
                    ],
                    'timestamp' => now()->toISOString(),
                ];
            } elseif ($defectRate > $thresholds['warning_defect_rate']) {
                $alerts[] = [
                    'severity' => 'warning',
                    'type' => 'part',
                    'identifier' => $part->part_number,
                    'defect_rate' => $defectRate,
                    'ppm' => $ppm,
                    'defects' => $defects,
                    'total' => $total,
                    'message' => "Parte {$part->part_number} con tasa de defectos en {$defectRate}%",
                    'recommended_actions' => [
                        'Monitorear de cerca',
                        'Revisar últimos resultados de inspección',
                    ],
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        $lots = DB::table('inspection_items')
            ->join('inspection_parts', 'inspection_items.inspection_part_id', '=', 'inspection_parts.id')
            ->join('inspections', 'inspection_parts.inspection_id', '=', 'inspections.id')
            ->where('inspections.company_id', $companyId)
            ->where('inspections.date', '>=', now()->startOfMonth())
            ->whereNull('inspections.deleted_at')
            ->whereNotNull('inspection_items.lot_date')
            ->where('inspection_items.lot_date', '!=', '')
            ->select(
                'inspection_items.lot_date',
                DB::raw('SUM(inspection_items.defects_qty) as defects'),
                DB::raw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) as total')
            )
            ->groupBy('inspection_items.lot_date')
            ->havingRaw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) > 0')
            ->get();

        foreach ($lots as $lot) {
            $total = (int) $lot->total;
            $defects = (int) $lot->defects;
            $defectRate = $total > 0 ? round(($defects / $total) * 100, 1) : 0;
            $ppm = $total > 0 ? (int) round(($defects / $total) * 1_000_000) : 0;

            if ($defectRate > $thresholds['critical_defect_rate']) {
                $alerts[] = [
                    'severity' => 'critical',
                    'type' => 'lot',
                    'identifier' => $lot->lot_date,
                    'defect_rate' => $defectRate,
                    'ppm' => $ppm,
                    'defects' => $defects,
                    'total' => $total,
                    'message' => "Lote {$lot->lot_date} tiene {$defectRate}% de tasa de defectos",
                    'recommended_actions' => [
                        'Contener producción inmediatamente',
                        'Poner en cuarentena el lote afectado',
                        'Revisar lote del proveedor',
                    ],
                    'timestamp' => now()->toISOString(),
                ];
            } elseif ($defectRate > $thresholds['warning_defect_rate']) {
                $alerts[] = [
                    'severity' => 'warning',
                    'type' => 'lot',
                    'identifier' => $lot->lot_date,
                    'defect_rate' => $defectRate,
                    'ppm' => $ppm,
                    'defects' => $defects,
                    'total' => $total,
                    'message' => "Lote {$lot->lot_date} con tasa de defectos en {$defectRate}%",
                    'recommended_actions' => [
                        'Monitorear de cerca',
                        'Verificar trazabilidad del lote',
                    ],
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        usort($alerts, fn ($a, $b) => $a['severity'] === 'critical' && $b['severity'] !== 'critical' ? -1 : 1);

        return array_slice($alerts, 0, 10);
    }

    /**
     * Count lots at risk (defect rate > threshold).
     */
    protected function getLotsAtRisk(int $companyId): int
    {
        $threshold = QualityThresholdService::defaults()['lot_at_risk_rate'];

        return DB::table('inspection_items')
            ->join('inspection_parts', 'inspection_items.inspection_part_id', '=', 'inspection_parts.id')
            ->join('inspections', 'inspection_parts.inspection_id', '=', 'inspections.id')
            ->where('inspections.company_id', $companyId)
            ->where('inspections.date', '>=', now()->startOfMonth())
            ->whereNull('inspections.deleted_at')
            ->whereNotNull('inspection_items.lot_date')
            ->where('inspection_items.lot_date', '!=', '')
            ->select(
                'inspection_items.lot_date',
                DB::raw('SUM(inspection_items.defects_qty) as defects'),
                DB::raw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) as total')
            )
            ->groupBy('inspection_items.lot_date')
            ->havingRaw('SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty) > 0')
            ->havingRaw('(SUM(inspection_items.defects_qty)::float / NULLIF(SUM(inspection_items.good_qty) + SUM(inspection_items.defects_qty), 0)) * 100 > ?', [$threshold])
            ->count();
    }

    /**
     * Recent inspections with part-level detail for drill-down.
     */
    protected function getRecentInspections(int $companyId): array
    {
        return \App\Models\Inspection::where('company_id', $companyId)
            ->with(['parts.items', 'assignedInspector:id,name', 'inspectors:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn ($ins) => [
                'id' => $ins->id,
                'reference_code' => $ins->reference_code,
                'date' => $ins->date->format('Y-m-d'),
                'shift' => $ins->shift,
                'project' => $ins->project,
                'area_line' => $ins->area_line,
                'status' => $ins->status,
                'inspector' => $ins->assignedInspector?->name ?? $ins->inspectors->first()?->name,
                'total_good' => $ins->total_good,
                'total_defects' => $ins->total_defects,
                'total' => $ins->total,
                'defect_rate' => $ins->defect_rate,
                'parts' => $ins->parts->map(fn ($part) => [
                    'part_number' => $part->part_number,
                    'total_good' => $part->part_total_good,
                    'total_defects' => $part->part_total_defects,
                    'total' => $part->part_total,
                    'defect_rate' => $part->part_defect_rate,
                    'items' => $part->items->map(fn ($item) => [
                        'serial_number' => $item->serial_number,
                        'lot_date' => $item->lot_date,
                        'good_qty' => $item->good_qty,
                        'defects_qty' => $item->defects_qty,
                    ]),
                ]),
            ])
            ->toArray();
    }
}
