<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MeasureResponseTime
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Time in nanoseconds
        $start = hrtime(true);

        $response = $next($request);

        // Convert time to milliseconds
        $ms = (hrtime(true) - $start) / 1_000_000;

        $response->headers->set('X-Response-Time', number_format($ms, 2).'ms');

        return $response;
    }
}
