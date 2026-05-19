<?php

namespace App\Models\Concerns;

use App\Tenancy\Tenancy;
use App\Tenancy\WorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Makes a model tenant-scoped by default.
 *
 * - Every query is automatically constrained to the current workspace
 *   (via the named WorkspaceScope global scope).
 * - workspace_id is auto-filled on create from the resolved tenant
 *   (and cannot be mass-assigned — see Course::$fillable).
 *
 * The scope only engages when a workspace is resolved, so console/seed code
 * still works; but in that context you must scope explicitly. The one
 * sanctioned bypass is withoutWorkspaceScope() with a deliberate where(),
 * used only by the by-id download/delete routes. The isolation test suite
 * asserts nothing else bypasses it.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('workspace_id') === null) {
                // Hard requirement in a tenant-resolved context: a row must
                // never be created without an owning workspace.
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
