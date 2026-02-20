<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->hasPermissionTo('users.view') || $user->hasPermissionTo('users.manage'), 403);

        $companyIds = $user->companyIds();
        $users = User::with('roles', 'companies:id,name')
            ->when(! $user->hasRole('super_admin') && $companyIds !== [], fn ($q) => $q->whereHas('companies', fn ($cq) => $cq->whereIn('companies.id', $companyIds)))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%"))
            ->orderBy('name')
            ->paginate(20)
            ->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'employee_number' => $u->employee_number,
                'status' => $u->status,
                'company_name' => $u->companies->isNotEmpty() ? $u->companies->pluck('name')->join(', ') : null,
                'roles' => $u->roles->pluck('name'),
                'last_login_at' => $u->last_login_at?->diffForHumans(),
            ]);

        return Inertia::render('App/Users/Index', [
            'users' => $users,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        abort_unless($user->hasPermissionTo('users.manage'), 403);

        $companyIds = $user->companyIds();
        $companies = $user->hasRole('super_admin')
            ? Company::where('status', 'active')->select('id', 'name')->get()
            : Company::whereIn('id', $companyIds)->where('status', 'active')->select('id', 'name')->get();

        $roles = Role::where('guard_name', 'web')->pluck('name');

        return Inertia::render('App/Users/Create', [
            'companies' => $companies,
            'roles' => $roles,
            'defaultCompanyId' => $companies->first()?->id,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermissionTo('users.manage'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'company_id' => 'nullable|exists:companies,id',
            'employee_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            ...collect($validated)->except('company_id')->all(),
            'company_id' => null,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($validated['role']);
        if (! empty($validated['company_id'])) {
            $user->companies()->sync([$validated['company_id']]);
        }

        return redirect()->route('app.users.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $user)
    {
        abort_unless(auth()->user()->hasPermissionTo('users.manage'), 403);

        $authUser = auth()->user();
        $companyIds = $authUser->companyIds();
        $companies = $authUser->hasRole('super_admin')
            ? Company::where('status', 'active')->select('id', 'name')->get()
            : Company::whereIn('id', $companyIds)->where('status', 'active')->select('id', 'name')->get();

        $roles = Role::where('guard_name', 'web')->pluck('name');
        $user->load('companies:id,name');

        return Inertia::render('App/Users/Edit', [
            'editUser' => [
                ...$user->only('id', 'name', 'email', 'employee_number', 'phone', 'status'),
                'company_id' => $user->companies->first()?->id,
                'role' => $user->roles->first()?->name,
            ],
            'companies' => $companies,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(auth()->user()->hasPermissionTo('users.manage'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'company_id' => 'nullable|exists:companies,id',
            'employee_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'company_id' => null,
            'employee_number' => $validated['employee_number'],
            'phone' => $validated['phone'],
            'status' => $validated['status'],
            ...($validated['password'] ? ['password' => Hash::make($validated['password'])] : []),
        ]);

        $user->syncRoles([$validated['role']]);
        $user->companies()->sync(! empty($validated['company_id']) ? [$validated['company_id']] : []);

        return redirect()->route('app.users.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->user()->hasPermissionTo('users.manage'), 403);
        abort_if($user->id === auth()->id(), 403, 'No puedes eliminar tu propia cuenta.');

        $user->delete();

        return redirect()->route('app.users.index')->with('success', 'Usuario eliminado.');
    }
}
