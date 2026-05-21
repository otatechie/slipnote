<?php

namespace App\Tenancy;

use App\Models\Workspace;
use RuntimeException;

/**
 * Holds the current workspace for the request. Bound as a singleton so the
 * BelongsToWorkspace global scope and controllers read the same resolved
 * tenant. Set once by ResolveWorkspace middleware.
 *
 * Console/queue context has no request and therefore no current workspace —
 * callers there must scope explicitly (withoutWorkspaceScope + an explicit
 * where), never rely on this.
 */
class Tenancy
{
    private ?Workspace $workspace = null;

    public function set(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function has(): bool
    {
        return $this->workspace !== null;
    }

    public function current(): Workspace
    {
        if ($this->workspace === null) {
            // Loud failure: a tenant-scoped query ran with no workspace
            // resolved. Better a 500 than a silent cross-tenant leak.
            throw new RuntimeException(
                'No current workspace. A tenant-scoped query ran outside a '
                .'workspace-resolved request.'
            );
        }

        return $this->workspace;
    }

    public function id(): ?int
    {
        return $this->workspace?->id;
    }
}
