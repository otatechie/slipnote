<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\OperatorSession;
use App\Support\VisitTally;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        if (! $workspace instanceof Workspace) {
            abort(404);
        }

        // Closed to everyone but the operator, who may still look (not change) it.
        if ($workspace->archived_at !== null && ! ($request->isMethodSafe() && OperatorSession::isActive())) {
            abort(410);
        }

        app(Tenancy::class)->set($workspace);

        if ($request->isMethodSafe() && ! VisitTally::isBot($request)) {
            $workspace->touchAccess();
        }

        return $next($request);
    }
}
