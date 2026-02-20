<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('companies.view') || $user->hasPermissionTo('companies.manage');
    }

    public function view(User $user, Company $company): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasPermissionTo('companies.view') && $user->hasAccessToCompany($company->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('companies.manage');
    }

    public function update(User $user, Company $company): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasPermissionTo('companies.manage') && $user->hasAccessToCompany($company->id);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin');
    }
}
