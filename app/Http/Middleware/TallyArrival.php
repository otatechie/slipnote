<?php

namespace App\Http\Middleware;

use App\Support\VisitTally;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Adds one to today's arrival count for this page's bucket. Counted after
 * the page is built and only for pages that rendered, and a failure is
 * reported and swallowed: a stats write must never cost anyone a page.
 */
class TallyArrival
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful() && VisitTally::shouldCount($request)) {
            try {
                VisitTally::record($request);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $response;
    }
}
