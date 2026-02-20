<?php

namespace App\Http\Controllers\App;

use App\Events\InspectionCompleted;
use App\Events\InspectionUpdated;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\InspectionPart;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspectionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Inspection::class);

        $user = auth()->user();

        $companyIds = $user->companyIds();
        $query = Inspection::with(['scheduledByUser:id,name', 'assignedInspector:id,name', 'inspectors:id,name', 'company:id,name', 'parts.items'])
            ->when(! $user->hasRole('super_admin') && $companyIds !== [], fn ($q) => $q->whereIn('company_id', $companyIds))
            ->when($user->hasRole('inspector'), fn ($q) => $q->where(function ($sub) use ($user) {
                $sub->where('assigned_inspector_id', $user->id)
                    ->orWhere('scheduled_by', $user->id)
                    ->orWhereHas('inspectors', fn ($iq) => $iq->where('user_id', $user->id));
            }));

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('reference_code', 'ilike', "%{$s}%")
                ->orWhereHas('company', fn ($cq) => $cq->where('name', 'ilike', "%{$s}%"))
                ->orWhere('project', 'ilike', "%{$s}%"));
        }
        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
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
                'inspector' => $ins->inspectors->isNotEmpty()
                    ? $ins->inspectors->pluck('name')->join(', ')
                    : $ins->assignedInspector?->name,
                'total_good' => $ins->total_good,
                'total_defects' => $ins->total_defects,
                'total' => $ins->total,
                'defect_rate' => $ins->defect_rate,
            ]);

        $companies = $user->hasRole('super_admin')
            ? Company::select('id', 'name')->orderBy('name')->get()
            : Company::whereIn('id', $user->companyIds())->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('App/Inspections/Index', [
            'inspections' => $inspections,
            'filters' => $request->only(['status', 'search', 'date_from', 'date_to', 'company_id']),
            'companies' => $companies,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Inspection::class);

        $user = auth()->user();

        $companyIds = $user->companyIds();
        $companies = $user->hasRole('super_admin')
            ? Company::where('status', 'active')->select('id', 'name')->get()
            : $user->companies()->where('status', 'active')->select('companies.id', 'companies.name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all();

        $inspectors = User::where('status', 'active')
            ->when(! $user->hasRole('super_admin') && $companyIds !== [], fn ($q) => $q->whereHas('companies', fn ($cq) => $cq->whereIn('companies.id', $companyIds)))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('App/Inspections/Create', [
            'companies' => $companies,
            'inspectors' => $inspectors,
            'defaultCompanyId' => $companies[0]['id'] ?? null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Inspection::class);

        $this->prepareInspectionRequest($request, false);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'date' => 'required|date',
            'shift' => 'nullable|string|max:10',
            'project' => 'nullable|string|max:255',
            'area_line' => 'nullable|string|max:255',
            'assigned_inspector_ids' => 'nullable|array',
            'assigned_inspector_ids.*' => 'exists:users,id',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'comment_general' => 'nullable|string',
            'parts' => 'nullable|array',
            'parts.*.part_number' => 'required|string|max:100',
            'parts.*.comment_part' => 'nullable|string',
            'parts.*.items' => 'nullable|array',
            'parts.*.items.*.serial_number' => 'nullable|string|max:100',
            'parts.*.items.*.lot_date' => 'nullable|string|max:50',
            'parts.*.items.*.good_qty' => 'required|integer|min:0',
            'parts.*.items.*.defects_qty' => 'required|integer|min:0',
        ]);

        $companyId = (int) $validated['company_id'];

        $inspectorIds = array_values(array_filter(array_unique($validated['assigned_inspector_ids'] ?? [])));
        $leadInspectorId = $inspectorIds[0] ?? null;

        DB::transaction(function () use ($validated, $companyId, $inspectorIds, $leadInspectorId) {
            $inspection = Inspection::create([
                'company_id' => $companyId,
                'date' => $validated['date'],
                'shift' => $validated['shift'] ?? null,
                'project' => $validated['project'] ?? null,
                'area_line' => $validated['area_line'] ?? null,
                'scheduled_by' => auth()->id(),
                'assigned_inspector_id' => $leadInspectorId,
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'comment_general' => $validated['comment_general'] ?? null,
                'status' => 'pending', // Always created as pending
                'reference_code' => Inspection::generateReferenceCode($companyId),
            ]);

            if (! empty($inspectorIds)) {
                $inspection->inspectors()->sync($inspectorIds);
            }

            if (! empty($validated['parts'])) {
                foreach ($validated['parts'] as $order => $partData) {
                    $part = InspectionPart::create([
                        'company_id' => $companyId,
                        'inspection_id' => $inspection->id,
                        'part_number' => $partData['part_number'],
                        'comment_part' => $partData['comment_part'] ?? null,
                        'order' => $order + 1,
                    ]);

                    if (! empty($partData['items'])) {
                        foreach ($partData['items'] as $itemData) {
                            InspectionItem::create([
                                'company_id' => $companyId,
                                'inspection_part_id' => $part->id,
                                'serial_number' => $itemData['serial_number'] ?? null,
                                'lot_date' => $itemData['lot_date'] ?? null,
                                'good_qty' => $itemData['good_qty'],
                                'defects_qty' => $itemData['defects_qty'],
                            ]);
                        }
                    }
                }
            }

            event(new InspectionUpdated($inspection));
        });

        return redirect()->route('app.inspections.index')->with('success', 'Inspección creada.');
    }

    public function show(Inspection $inspection)
    {
        $this->authorize('view', $inspection);

        $user = auth()->user();
        $inspection->load(['scheduledByUser:id,name', 'assignedInspector:id,name', 'company:id,name', 'parts.items']);

        return Inertia::render('App/Inspections/Show', [
            'inspection' => $this->formatInspection($inspection),
            'canStart' => $user->can('start', $inspection),
            'canComplete' => $user->can('complete', $inspection),
        ]);
    }

    public function edit(Inspection $inspection)
    {
        $this->authorize('update', $inspection);

        $inspection->load(['parts.items', 'inspectors:id,name']);
        $user = auth()->user();
        $isInspector = $user->hasRole('inspector');

        $inspectors = User::where('status', 'active')
            ->when(! $user->hasRole('super_admin'), fn ($q) => $q->whereHas('companies', fn ($cq) => $cq->where('companies.id', $inspection->company_id)))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('App/Inspections/Edit', [
            'inspection' => $this->formatInspection($inspection),
            'inspectors' => $inspectors,
            'isInspector' => $isInspector,
            'canComplete' => $user->can('complete', $inspection),
        ]);
    }

    public function update(Request $request, Inspection $inspection)
    {
        $this->authorize('update', $inspection);

        $this->prepareInspectionRequest($request, true);

        $user = auth()->user();
        $isInspector = $user->hasRole('inspector');

        $validated = $request->validate([
            'date' => 'required|date',
            'shift' => 'nullable|string|max:10',
            'project' => 'nullable|string|max:255',
            'area_line' => 'nullable|string|max:255',
            'assigned_inspector_ids' => 'nullable|array',
            'assigned_inspector_ids.*' => 'exists:users,id',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'comment_general' => 'nullable|string',
            'parts' => 'required|array|min:1',
            'parts.*.id' => 'nullable|integer',
            'parts.*.part_number' => 'required|string|max:100',
            'parts.*.comment_part' => 'nullable|string',
            'parts.*.items' => 'required|array|min:1',
            'parts.*.items.*.id' => 'nullable|integer',
            'parts.*.items.*.serial_number' => 'nullable|string|max:100',
            'parts.*.items.*.lot_date' => 'nullable|string|max:50',
            'parts.*.items.*.good_qty' => 'required|integer|min:0',
            'parts.*.items.*.defects_qty' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $inspection, $isInspector) {
            // Inspector: can only update items + comment, status stays in_progress
            if ($isInspector) {
                $inspection->update([
                    'comment_general' => $validated['comment_general'] ?? null,
                ]);
            } else {
                $inspectorIds = array_values(array_filter(array_unique($validated['assigned_inspector_ids'] ?? [])));
                $leadInspectorId = $inspectorIds[0] ?? null;
                $inspection->update([
                    'date' => $validated['date'],
                    'shift' => $validated['shift'] ?? null,
                    'project' => $validated['project'] ?? null,
                    'area_line' => $validated['area_line'] ?? null,
                    'assigned_inspector_id' => $leadInspectorId,
                    'start_time' => $validated['start_time'] ?? null,
                    'end_time' => $validated['end_time'] ?? null,
                    'comment_general' => $validated['comment_general'] ?? null,
                    // Status is NOT changed on regular save
                ]);
                $inspection->inspectors()->sync($inspectorIds);
            }

            $existingPartIds = [];
            foreach ($validated['parts'] as $order => $partData) {
                $part = isset($partData['id'])
                    ? InspectionPart::findOrFail($partData['id'])
                    : new InspectionPart();

                $part->fill([
                    'company_id' => $inspection->company_id,
                    'inspection_id' => $inspection->id,
                    'part_number' => $partData['part_number'],
                    'comment_part' => $partData['comment_part'] ?? null,
                    'order' => $order + 1,
                ]);
                $part->save();
                $existingPartIds[] = $part->id;

                $existingItemIds = [];
                foreach ($partData['items'] as $itemData) {
                    $item = isset($itemData['id'])
                        ? InspectionItem::findOrFail($itemData['id'])
                        : new InspectionItem();

                    $item->fill([
                        'company_id' => $inspection->company_id,
                        'inspection_part_id' => $part->id,
                        'serial_number' => $itemData['serial_number'] ?? null,
                        'lot_date' => $itemData['lot_date'] ?? null,
                        'good_qty' => $itemData['good_qty'],
                        'defects_qty' => $itemData['defects_qty'],
                    ]);
                    $item->save();
                    $existingItemIds[] = $item->id;
                }

                // Delete removed items
                InspectionItem::where('inspection_part_id', $part->id)
                    ->whereNotIn('id', $existingItemIds)
                    ->delete();
            }

            // Delete removed parts (cascade deletes items)
            InspectionPart::where('inspection_id', $inspection->id)
                ->whereNotIn('id', $existingPartIds)
                ->delete();

            event(new InspectionUpdated($inspection->fresh()));
        });

        return redirect()->route('app.inspections.show', $inspection)->with('success', 'Inspección guardada.');
    }

    /**
     * Start an inspection (pending -> in_progress).
     */
    public function start(Inspection $inspection)
    {
        $this->authorize('start', $inspection);

        $inspection->update([
            'status' => 'in_progress',
            'start_time' => $inspection->start_time ?? now()->format('H:i'),
        ]);

        event(new InspectionUpdated($inspection));

        return redirect()->route('app.inspections.edit', $inspection)->with('success', 'Inspección iniciada. Puedes comenzar a capturar items.');
    }

    /**
     * Complete an inspection (in_progress -> completed).
     * Only admin/supervisor can do this.
     */
    public function complete(Inspection $inspection)
    {
        $this->authorize('complete', $inspection);

        $inspection->load('parts.items');

        // Validate completeness
        if ($inspection->parts->isEmpty()) {
            return back()->with('error', 'Debe tener al menos una parte.');
        }

        foreach ($inspection->parts as $part) {
            if ($part->items->isEmpty()) {
                return back()->with('error', "La parte {$part->part_number} no tiene items.");
            }
        }

        $inspection->update([
            'status' => 'completed',
            'end_time' => $inspection->end_time ?? now()->format('H:i'),
        ]);

        event(new InspectionCompleted($inspection));

        return redirect()->route('app.inspections.index')->with('success', 'Inspección completada.');
    }

    public function destroy(Inspection $inspection)
    {
        $this->authorize('delete', $inspection);

        $inspection->delete();

        return redirect()->route('app.inspections.index')->with('success', 'Inspección eliminada.');
    }

    public function exportPdf(Inspection $inspection)
    {
        $this->authorize('export', $inspection);

        $inspection->load('parts.items', 'scheduledByUser', 'assignedInspector', 'company');

        $pdf = Pdf::loadView('exports.inspection-pdf', [
            'inspection' => $inspection,
            'company' => $inspection->company,
        ]);

        return $pdf->download("inspeccion-{$inspection->reference_code}.pdf");
    }

    public function exportCsv(Inspection $inspection): StreamedResponse
    {
        $this->authorize('export', $inspection);

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
        }, "inspeccion-{$inspection->reference_code}.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * Factory-floor capture page (progressive single-record flow).
     * Only accessible when the inspection is in_progress.
     */
    public function capture(Inspection $inspection)
    {
        $this->authorize('update', $inspection);

        if (! $inspection->isInProgress()) {
            return redirect()->route('app.inspections.show', $inspection)
                ->with('error', 'Solo se puede capturar en inspecciones en progreso.');
        }

        $inspection->load(['scheduledByUser:id,name', 'assignedInspector:id,name', 'company:id,name', 'parts.items']);

        return Inertia::render('App/Inspections/Capture', [
            'inspection' => $this->formatInspection($inspection),
            'qualityThresholds' => [
                'green' => 95,
                'amber' => 90,
            ],
        ]);
    }

    /**
     * Add a single inspection record (item) via the factory capture flow.
     * Finds or creates the InspectionPart by part_number, then creates the InspectionItem.
     * Redirects back to the capture page so Inertia re-renders with fresh totals.
     */
    public function storeItem(Request $request, Inspection $inspection)
    {
        $this->authorize('update', $inspection);

        if (! $inspection->isInProgress()) {
            return back()->withErrors(['general' => 'La inspección no está en progreso.']);
        }

        $validated = $request->validate([
            'part_number' => 'required|string|max:100',
            'serial_number' => 'required|string|max:100',
            'lot_date' => 'nullable|string|max:50',
            'good_qty' => 'required|integer|min:0',
            'defects_qty' => 'required|integer|min:0',
        ]);

        $validated['good_qty'] = (int) $validated['good_qty'];
        $validated['defects_qty'] = (int) $validated['defects_qty'];

        DB::transaction(function () use ($validated, $inspection) {
            $part = InspectionPart::firstOrCreate(
                [
                    'inspection_id' => $inspection->id,
                    'part_number' => $validated['part_number'],
                ],
                [
                    'company_id' => $inspection->company_id,
                    'order' => ($inspection->parts()->max('order') ?? 0) + 1,
                ]
            );

            InspectionItem::create([
                'company_id' => $inspection->company_id,
                'inspection_part_id' => $part->id,
                'serial_number' => $validated['serial_number'],
                'lot_date' => $validated['lot_date'] ?? null,
                'good_qty' => $validated['good_qty'],
                'defects_qty' => $validated['defects_qty'],
            ]);
        });

        event(new InspectionUpdated($inspection->fresh()));

        return redirect()->route('app.inspections.capture', $inspection)
            ->with('success', 'Registro guardado.');
    }

    /**
     * Update a single inspection item from the capture flow.
     */
    public function updateItem(Request $request, Inspection $inspection, InspectionItem $item)
    {
        $this->authorize('update', $inspection);

        if (! $inspection->isInProgress()) {
            return back()->withErrors(['general' => 'La inspección no está en progreso.']);
        }

        // Ensure item belongs to this inspection
        if ($item->inspectionPart->inspection_id !== $inspection->id) {
            abort(404);
        }

        $validated = $request->validate([
            'serial_number' => 'required|string|max:100',
            'lot_date' => 'nullable|string|max:50',
            'good_qty' => 'required|integer|min:0',
            'defects_qty' => 'required|integer|min:0',
        ]);

        $item->update([
            'serial_number' => $validated['serial_number'],
            'lot_date' => $validated['lot_date'] ?? null,
            'good_qty' => (int) $validated['good_qty'],
            'defects_qty' => (int) $validated['defects_qty'],
        ]);

        event(new InspectionUpdated($inspection->fresh()));

        return redirect()->route('app.inspections.capture', $inspection)
            ->with('success', 'Registro actualizado.');
    }

    /**
     * Delete a single inspection item from the capture flow.
     */
    public function destroyItem(Inspection $inspection, InspectionItem $item)
    {
        $this->authorize('update', $inspection);

        if (! $inspection->isInProgress()) {
            return back()->withErrors(['general' => 'La inspección no está en progreso.']);
        }

        // Ensure item belongs to this inspection
        if ($item->inspectionPart->inspection_id !== $inspection->id) {
            abort(404);
        }

        $part = $item->inspectionPart;
        $item->delete();

        // If the part has no more items, remove it too
        if ($part->items()->count() === 0) {
            $part->delete();
        }

        event(new InspectionUpdated($inspection->fresh()));

        return redirect()->route('app.inspections.capture', $inspection)
            ->with('success', 'Registro eliminado.');
    }

    /**
     * Normalize request data before validation so frontend empty strings and
     * missing numerics are accepted (nullable fields → null, item quantities → int).
     * Times are normalized to H:i (e.g. "08:00:00" → "08:00").
     */
    protected function prepareInspectionRequest(Request $request, bool $forUpdate): void
    {
        $merge = [
            'assigned_inspector_ids' => array_values(array_filter((array) $request->input('assigned_inspector_ids', []))),
            'start_time' => $this->normalizeTimeToHi($request->input('start_time')),
            'end_time' => $this->normalizeTimeToHi($request->input('end_time')),
        ];
        if (! $forUpdate) {
            $merge['company_id'] = $request->input('company_id') ?: null;
        }
        $request->merge($merge);

        $parts = $request->input('parts', []);
        foreach ($parts as $pi => $part) {
            foreach ($part['items'] ?? [] as $ii => $item) {
                $parts[$pi]['items'][$ii]['good_qty'] = (int) ($item['good_qty'] ?? 0);
                $parts[$pi]['items'][$ii]['defects_qty'] = (int) ($item['defects_qty'] ?? 0);
            }
        }
        $request->merge(['parts' => $parts]);
    }

    /**
     * Normalize a time value to H:i format (e.g. "08:00:00" or "8:0" → "08:00").
     * Returns null for empty or invalid values.
     */
    protected function normalizeTimeToHi(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $parts = explode(':', $value);
        if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            $h = (int) $parts[0];
            $m = (int) $parts[1];
            if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59) {
                return sprintf('%02d:%02d', $h, $m);
            }
        }
        $dt = \DateTime::createFromFormat('H:i', $value)
            ?: \DateTime::createFromFormat('H:i:s', $value)
            ?: \DateTime::createFromFormat(\DateTime::ATOM, $value);
        return $dt ? $dt->format('H:i') : null;
    }

    protected function formatInspection(Inspection $inspection): array
    {
        $inspection->loadMissing('inspectors:id,name');
        $inspectorNames = $inspection->inspectors->isNotEmpty()
            ? $inspection->inspectors->pluck('name')->all()
            : ($inspection->assignedInspector ? [$inspection->assignedInspector->name] : []);

        return [
            'id' => $inspection->id,
            'company_id' => $inspection->company_id,
            'company_name' => $inspection->company?->name,
            'reference_code' => $inspection->reference_code,
            'date' => $inspection->date->format('Y-m-d'),
            'shift' => $inspection->shift,
            'project' => $inspection->project,
            'area_line' => $inspection->area_line,
            'status' => $inspection->status,
            'start_time' => $inspection->start_time,
            'end_time' => $inspection->end_time,
            'comment_general' => $inspection->comment_general,
            'scheduled_by' => $inspection->scheduledByUser?->name,
            'assigned_inspector_id' => $inspection->assigned_inspector_id,
            'assigned_inspector_ids' => $inspection->inspectors->pluck('id')->all(),
            'inspector' => implode(', ', $inspectorNames),
            'inspectors' => $inspection->inspectors->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
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
        ];
    }
}
