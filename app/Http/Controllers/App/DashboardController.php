<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $companyIds = $user->companyIds();
        $isSuperAdmin = $user->hasRole('super_admin');

        $isInspector = $user->hasRole('inspector');

        $query = Inspection::query();
        if (! $isSuperAdmin && $companyIds !== []) {
            $query->whereIn('company_id', $companyIds);
        }
        if ($isInspector) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_inspector_id', $user->id)
                    ->orWhere('scheduled_by', $user->id)
                    ->orWhereHas('inspectors', fn ($iq) => $iq->where('user_id', $user->id));
            });
        }

        $totalInspections = (clone $query)->count();
        $activeInspections = (clone $query)->where('status', 'in_progress')->count();
        $completedToday = (clone $query)->where('status', 'completed')->whereDate('updated_at', today())->count();

        $monthItems = DB::table('inspection_items')
            ->join('inspection_parts', 'inspection_items.inspection_part_id', '=', 'inspection_parts.id')
            ->join('inspections', 'inspection_parts.inspection_id', '=', 'inspections.id')
            ->when(! $isSuperAdmin && $companyIds !== [], fn ($q) => $q->whereIn('inspections.company_id', $companyIds))
            ->when($isInspector, fn ($q) => $q->where(function ($sub) use ($user) {
                $sub->where('inspections.assigned_inspector_id', $user->id)
                    ->orWhere('inspections.scheduled_by', $user->id)
                    ->orWhereExists(fn ($eq) => $eq->select(DB::raw(1))->from('inspection_inspector')->whereColumn('inspection_inspector.inspection_id', 'inspections.id')->where('inspection_inspector.user_id', $user->id));
            }))
            ->where('inspections.date', '>=', now()->startOfMonth())
            ->whereNull('inspections.deleted_at')
            ->select(
                DB::raw('COALESCE(SUM(inspection_items.good_qty), 0) as good'),
                DB::raw('COALESCE(SUM(inspection_items.defects_qty), 0) as defects')
            )
            ->first();

        $recentInspections = Inspection::query()
            ->when(! $isSuperAdmin && $companyIds !== [], fn ($q) => $q->whereIn('company_id', $companyIds))
            ->when($isInspector, fn ($q) => $q->where(function ($sub) use ($user) {
                $sub->where('assigned_inspector_id', $user->id)
                    ->orWhere('scheduled_by', $user->id)
                    ->orWhereHas('inspectors', fn ($iq) => $iq->where('user_id', $user->id));
            }))
            ->with(['scheduledByUser:id,name', 'assignedInspector:id,name', 'company:id,name'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($ins) => [
                'id' => $ins->id,
                'reference_code' => $ins->reference_code,
                'company_name' => $ins->company?->name,
                'date' => $ins->date->format('Y-m-d'),
                'status' => $ins->status,
                'project' => $ins->project,
                'inspector' => $ins->assignedInspector?->name,
                'updated_at' => $ins->updated_at->diffForHumans(),
            ]);

        return Inertia::render('App/Dashboard', [
            'stats' => [
                'total_inspections' => $totalInspections,
                'active_inspections' => $activeInspections,
                'completed_today' => $completedToday,
                'month_good' => (int) $monthItems->good,
                'month_defects' => (int) $monthItems->defects,
                'month_total' => (int) $monthItems->good + (int) $monthItems->defects,
                'month_defect_rate' => ((int) $monthItems->good + (int) $monthItems->defects) > 0
                    ? round(((int) $monthItems->defects / ((int) $monthItems->good + (int) $monthItems->defects)) * 100, 2)
                    : 0,
            ],
            'recentInspections' => $recentInspections,
        ]);
    }
}
