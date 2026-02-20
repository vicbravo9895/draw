<?php

namespace App\Http\Middleware;

use App\Scopes\CompanyScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAccess
{
    /**
     * Models that should have company scope applied in portal context.
     */
    protected array $scopedModels = [
        \App\Models\Inspection::class,
        \App\Models\InspectionPart::class,
        \App\Models\InspectionItem::class,
        \App\Models\DefectTag::class,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $viewer = auth('portal')->user();

        if (! $viewer || ! $viewer->company_id) {
            abort(403, 'Unauthorized portal access.');
        }

        $companyId = (int) $viewer->company_id;

        // Bind company_id to the container for global scope resolution
        app()->instance('portal.company_id', $companyId);

        // Apply global scope to all company-scoped models
        foreach ($this->scopedModels as $model) {
            $model::addGlobalScope(new CompanyScope($companyId));
        }

        // After the request, verify any route-model-bound resource belongs to this company
        $response = $next($request);

        return $response;
    }
}
