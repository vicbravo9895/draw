<?php

use App\Models\CompanyViewer;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Backoffice channel: employees assigned to this company (or super_admin)
Broadcast::channel('company.{companyId}', function (User $user, int $companyId) {
    return $user->hasAccessToCompany($companyId);
});

// Portal channel: company viewers. Usar guard 'portal' para que retrieveUser() use auth('portal')->user()
Broadcast::channel('portal.company.{companyId}', function (CompanyViewer $viewer, int $companyId) {
    return (int) $viewer->company_id === $companyId;
}, ['guards' => ['portal']]);
