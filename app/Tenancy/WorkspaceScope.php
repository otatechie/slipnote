<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = app(Tenancy::class);
        if ($tenancy->has()) {
            $builder->where(
                $model->getTable().'.workspace_id',
                $tenancy->id()
            );
        }
    }
}
