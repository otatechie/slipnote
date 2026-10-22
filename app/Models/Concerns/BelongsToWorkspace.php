<?php

namespace App\Models\Concerns;

use App\Tenancy\Tenancy;
use App\Tenancy\WorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('workspace_id') === null) {
                $model->setAttribute(
                    'workspace_id',
                    app(Tenancy::class)->current()->id
                );
            }
        });
    }

    /** Sanctioned, explicit bypass for by-id routes that resolve their own tenant. */
    public function scopeWithoutWorkspaceScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(WorkspaceScope::class);
    }
}
