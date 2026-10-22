<?php

namespace App\Http\Middleware;

use App\Support\VisitTally;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
