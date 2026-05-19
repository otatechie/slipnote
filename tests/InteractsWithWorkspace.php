<?php

namespace Tests;

use App\Models\Workspace;
use App\Tenancy\Tenancy;

/**
 * Test support: every existing single-tenant test now runs *inside* a
 * workspace. This provisions one, sets it as the resolved tenant (so
 * Course::create / scoped queries work exactly as before), and exposes the
 * workspace + its real owner secret for owner-mode tests and route params.
 *
 * Behaviour inside a workspace is unchanged — that's the whole point of the
 * trait-based scoping, and why these tests need only setup changes.
 */
trait InteractsWithWorkspace
{
    protected Workspace $workspace;

    protected string $ownerSecret;

    protected function setUpWorkspace(): void
    {
        [$this->workspace, $this->ownerSecret] = Workspace::provision('Test Workspace');

        // Resolve the tenant for direct model use (Course::create, etc.).
        // HTTP/Livewire requests re-resolve it via the route binding.
        app(Tenancy::class)->set($this->workspace);
    }

    /**
     * Make the given workspace the resolved tenant — mirrors what the route
     * middleware does for a real request. Livewire component tests must NOT
     * pass `workspace` as a param (it would bind the slug string onto the
     * typed Workspace property); the component reads the resolved tenant.
     */
    protected function actingInWorkspace(Workspace $workspace): void
    {
        app(Tenancy::class)->set($workspace);
    }

    protected function unlockOwnerSession(?Workspace $workspace = null): void
    {
        $workspace ??= $this->workspace;

        session([$workspace->ownerSessionKey() => true]);
    }

    /**
     * Route params for workspace-scoped URLs (named routes need the slug).
     */
    protected function wsParams(array $extra = []): array
    {
        return array_merge(['workspace' => $this->workspace->slug], $extra);
    }

    /**
     * Livewire component params. The component resolves its workspace from
     * the Tenancy singleton (set in setUpWorkspace), NOT from a mount param —
     * passing 'workspace' here would bind the slug string onto the typed
     * Workspace property. So this passes only non-workspace params.
     */
    protected function lwParams(array $extra = []): array
    {
        return $extra;
    }
}
