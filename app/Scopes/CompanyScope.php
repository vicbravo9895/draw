<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function __construct(
        protected int $companyId
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable() . '.company_id', $this->companyId);
    }
}
