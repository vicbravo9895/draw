<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DefectTag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DefectTagController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->hasPermissionTo('defect_tags.view') || $user->hasPermissionTo('defect_tags.manage'), 403);

        $companyIds = $user->companyIds();
        $tags = DefectTag::query()
            ->when(! $user->hasRole('super_admin') && $companyIds !== [], fn ($q) => $q->whereIn('company_id', $companyIds))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(30);

        $data = [
            'tags' => $tags,
            'filters' => $request->only('search'),
            'canManage' => $user->hasPermissionTo('defect_tags.manage'),
        ];

        // Super admin sees company selector; others see only their assigned companies
        if ($user->hasRole('super_admin')) {
            $data['companies'] = Company::orderBy('name')->get(['id', 'name']);
        } elseif ($companyIds !== []) {
            $data['companies'] = Company::whereIn('id', $companyIds)->orderBy('name')->get(['id', 'name']);
        }

        return Inertia::render('App/DefectTags/Index', $data);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->hasPermissionTo('defect_tags.manage'), 403);

        $rules = [
            'name' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ];

        $companyIds = $user->companyIds();
        if ($user->hasRole('super_admin') || count($companyIds) !== 1) {
            $rules['company_id'] = 'required|exists:companies,id';
        }

        $validated = $request->validate($rules);

        $companyId = count($companyIds) === 1 ? $companyIds[0] : ($validated['company_id'] ?? null);
        abort_if(! $companyId || ! $user->hasAccessToCompany((int) $companyId), 403);

        DefectTag::create([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'company_id' => $companyId,
        ]);

        return back()->with('success', 'Etiqueta creada.');
    }

    public function update(Request $request, DefectTag $defectTag)
    {
        abort_unless(auth()->user()->hasPermissionTo('defect_tags.manage'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $defectTag->update($validated);

        return back()->with('success', 'Etiqueta actualizada.');
    }

    public function destroy(DefectTag $defectTag)
    {
        abort_unless(auth()->user()->hasPermissionTo('defect_tags.manage'), 403);

        $defectTag->delete();

        return back()->with('success', 'Etiqueta eliminada.');
    }
}
