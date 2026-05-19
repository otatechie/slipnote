<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope constraining a model to the current workspace.
 *
 * Engages only when a tenant is resolved (so console/seed code still runs;
 * those contexts must scope explicitly). Named (not anonymous) so the
 * by-id download/delete routes can remove it precisely via
 * withoutGlobalScope(WorkspaceScope::class) and apply their own check.
 */
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
