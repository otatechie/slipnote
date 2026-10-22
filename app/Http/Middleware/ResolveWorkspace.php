<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
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

        app(Tenancy::class)->set($workspace);

        if ($request->isMethodSafe() && ! VisitTally::isBot($request)) {
            $workspace->touchAccess();
        }

        return $next($request);
    }
}
