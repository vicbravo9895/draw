<?php

namespace App\Traits;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        // Auto-assign company_id on creating (for backoffice context)
        static::creating(function ($model) {
            if (empty($model->company_id) && auth('web')->check()) {
                $model->company_id = auth('web')->user()->company_id;
            }
        });

        // Apply company scope when in portal context
        if (app()->bound('portal.company_id')) {
            static::addGlobalScope(new CompanyScope(app('portal.company_id')));
        }
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where($this->getTable() . '.company_id', $companyId);
    }
}
