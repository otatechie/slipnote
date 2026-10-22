<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\VisitTally;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the {workspace} route segment to a Workspace and sets it as the
 * current tenant before any tenant-scoped query runs. Unknown slug → 404.
 *
 * Applied to all /{workspace}/... routes. Must run before the route's
 * controller so the WorkspaceScope has a tenant to read.
 */
class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        // The route param is bound to a Workspace by slug (see web.php
        // Route::bind), so it arrives here already resolved — or 404'd.
        $workspace = $request->route('workspace');

        if (! $workspace instanceof Workspace) {
            abort(404);
        }

        app(Tenancy::class)->set($workspace);

        // GET only: a POST is already covered by the page view that preceded
        // it, and this shouldn't add a write to every upload. People only:
        // a link-preview fetcher re-reading a link pasted in a group chat
        // kept dead boards looking opened (and safe from the cleanup).
        if ($request->isMethodSafe() && ! VisitTally::isBot($request)) {
            $workspace->touchAccess();
        }

        return $next($request);
    }
}
