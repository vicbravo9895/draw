<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Inspection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspectionController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth('portal')->user()->company_id;

        $query = Inspection::where('company_id', $companyId)
            ->with(['scheduledByUser:id,name', 'assignedInspector:id,name', 'parts.items']);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('project')) {
            $query->where('project', 'ilike', '%' . $request->project . '%');
        }

        if ($request->filled('area_line')) {
            $query->where('area_line', 'ilike', '%' . $request->area_line . '%');
        }

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_code', 'ilike', "%{$search}%")
                    ->orWhereHas('company', fn ($cq) => $cq->where('name', 'ilike', "%{$search}%"))
                    ->orWhere('project', 'ilike', "%{$search}%");
            });
        }

        $inspections = $query->orderByDesc('date')->orderByDesc('id')
            ->paginate(20)
            ->through(fn ($ins) => [
                'id' => $ins->id,
                'reference_code' => $ins->reference_code,
                'company_name' => $ins->company?->name,
                'date' => $ins->date->format('Y-m-d'),
                'shift' => $ins->shift,
                'project' => $ins->project,
                'area_line' => $ins->area_line,
                'status' => $ins->status,
                'scheduled_by' => $ins->scheduledByUser?->name,
                'inspector' => $ins->assignedInspector?->name,
                'total_good' => $ins->total_good,
                'total_defects' => $ins->total_defects,
                'total' => $ins->total,
                'defect_rate' => $ins->defect_rate,
            ]);

        return Inertia::render('Portal/Inspections/Index', [
            'inspections' => $inspections,
            'filters' => $request->only(['status', 'project', 'area_line', 'date_from', 'date_to', 'search']),
            'company' => auth('portal')->user()->company->only('name', 'allow_exports'),
        ]);
    }

    public function show(Inspection $inspection)
    {
        $companyId = auth('portal')->user()->company_id;

        // Strict company isolation check
        if ((int) $inspection->company_id !== $companyId) {
            abort(403, 'No tienes acceso a esta inspección.');
        }

        $inspection->load([
            'company:id,name',
            'scheduledByUser:id,name',
            'assignedInspector:id,name',
            'parts.items',
        ]);

        return Inertia::render('Portal/Inspections/Show', [
            'inspection' => [
                'id' => $inspection->id,
                'reference_code' => $inspection->reference_code,
                'company_name' => $inspection->company?->name,
                'date' => $inspection->date->format('Y-m-d'),
                'shift' => $inspection->shift,
                'project' => $inspection->project,
                'area_line' => $inspection->area_line,
                'status' => $inspection->status,
                'start_time' => $inspection->start_time,
                'end_time' => $inspection->end_time,
                'comment_general' => $inspection->comment_general,
                'scheduled_by' => $inspection->scheduledByUser?->name,
                'inspector' => $inspection->assignedInspector?->name,
                'total_good' => $inspection->total_good,
                'total_defects' => $inspection->total_defects,
                'total' => $inspection->total,
                'defect_rate' => $inspection->defect_rate,
                'parts' => $inspection->parts->map(fn ($part) => [
                    'id' => $part->id,
                    'part_number' => $part->part_number,
                    'comment_part' => $part->comment_part,
                    'order' => $part->order,
                    'total_good' => $part->part_total_good,
                    'total_defects' => $part->part_total_defects,
                    'total' => $part->part_total,
                    'defect_rate' => $part->part_defect_rate,
                    'items' => $part->items->map(fn ($item) => [
                        'id' => $item->id,
                        'serial_number' => $item->serial_number,
                        'lot_date' => $item->lot_date,
                        'good_qty' => $item->good_qty,
                        'defects_qty' => $item->defects_qty,
                        'total_qty' => $item->total_qty,
                    ]),
                ]),
                'created_at' => $inspection->created_at->format('Y-m-d H:i'),
            ],
            'company' => auth('portal')->user()->company->only('name', 'allow_exports'),
        ]);
    }

    public function exportPdf(Inspection $inspection)
    {
        $companyId = auth('portal')->user()->company_id;
        $company = auth('portal')->user()->company;

        if ((int) $inspection->company_id !== $companyId) {
            abort(403);
        }

        if (! $company->allow_exports) {
            abort(403, 'Exportaciones no habilitadas.');
        }

        $inspection->load('parts.items', 'scheduledByUser', 'assignedInspector');

        $pdf = Pdf::loadView('exports.inspection-pdf', [
            'inspection' => $inspection,
            'company' => $company,
        ]);

        return $pdf->download("inspeccion-{$inspection->reference_code}.pdf");
    }

    public function exportCsv(Inspection $inspection): StreamedResponse
    {
        $companyId = auth('portal')->user()->company_id;
        $company = auth('portal')->user()->company;

        if ((int) $inspection->company_id !== $companyId) {
            abort(403);
        }

        if (! $company->allow_exports) {
            abort(403, 'Exportaciones no habilitadas.');
        }

        $inspection->load('parts.items');

        return response()->streamDownload(function () use ($inspection) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Referencia', 'Fecha', 'Turno', 'Parte', 'S/N', 'Lote', 'Buenas', 'Malas', 'Total']);

            foreach ($inspection->parts as $part) {
                foreach ($part->items as $item) {
                    fputcsv($handle, [
                        $inspection->reference_code,
                        $inspection->date->format('Y-m-d'),
                        $inspection->shift,
                        $part->part_number,
                        $item->serial_number,
                        $item->lot_date,
                        $item->good_qty,
                        $item->defects_qty,
                        $item->total_qty,
                    ]);
                }
            }

            fclose($handle);
        }, "inspeccion-{$inspection->reference_code}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
