<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Company::class);

        $user = auth()->user();

        $companyIds = $user->companyIds();
        $companies = Company::query()
            ->when(! $user->hasRole('super_admin'), fn ($q) => $q->whereIn('id', $companyIds ?: [0]))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('App/Companies/Index', [
            'companies' => $companies,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Company::class);

        return Inertia::render('App/Companies/Create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Company::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'public_code' => 'required|string|max:50|unique:companies,public_code',
            'status' => 'required|in:active,inactive',
            'timezone' => 'required|string',
            'contact_email' => 'nullable|email',
            'allowed_domains' => 'nullable|array',
            'allowed_emails' => 'nullable|array',
            'notes' => 'nullable|string',
            'allow_exports' => 'boolean',
        ]);

        Company::create($validated);

        return redirect()->route('app.companies.index')->with('success', 'Empresa creada.');
    }

    public function edit(Company $company)
    {
        $this->authorize('update', $company);

        return Inertia::render('App/Companies/Edit', [
            'company' => $company,
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'public_code' => 'required|string|max:50|unique:companies,public_code,' . $company->id,
            'status' => 'required|in:active,inactive',
            'timezone' => 'required|string',
            'contact_email' => 'nullable|email',
            'allowed_domains' => 'nullable|array',
            'allowed_emails' => 'nullable|array',
            'notes' => 'nullable|string',
            'allow_exports' => 'boolean',
        ]);

        $company->update($validated);

        return redirect()->route('app.companies.index')->with('success', 'Empresa actualizada.');
    }

    public function show(Company $company)
    {
        $this->authorize('view', $company);

        return Inertia::render('App/Companies/Edit', [
            'company' => $company,
            'readonly' => true,
        ]);
    }

    public function destroy(Company $company)
    {
        $this->authorize('delete', $company);

        $company->delete();

        return redirect()->route('app.companies.index')->with('success', 'Empresa eliminada.');
    }
}
