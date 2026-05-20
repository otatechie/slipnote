<?php

namespace App\Tenancy;

use App\Models\Workspace;

/**
 * Shared Livewire boilerplate for any component that operates inside a
 * workspace. Two mechanical things every such component needs:
 *
 *   1. Resolve the current workspace from the Tenancy singleton at mount
 *      time — the resolved tenant is the single source of truth (set by
 *      ResolveWorkspace middleware on the initial GET).
 *
 *   2. RE-resolve it on every subsequent Livewire request: the
 *      POST /livewire/update endpoint bypasses the {workspace} route and
 *      its middleware, so without this any wire:model/action would hit a
 *      scoped query with no resolved tenant and 500.
 *
 * Forgetting step 2 is a silent footgun — pages work on first load, then
 * break the moment anything is interactive. The trait keeps it impossible
 * to forget: components include the trait and the hydrate() hook fires
 * automatically.
 */
trait ResolvesWorkspaceTenant
{
    public Workspace $workspace;

    public function hydrateResolvesWorkspaceTenant(): void
    {
        if (isset($this->workspace)) {
            app(Tenancy::class)->set($this->workspace);
        }
    }

    /** Read the resolved tenant. Call this from mount(). */
    protected function resolveWorkspaceTenant(): void
    {
        $this->workspace = app(Tenancy::class)->current();
    }
}
