<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts every HTTP request (and those answered with a 5xx) into hourly
 * buckets for the System Dashboard's error-rate widget. Recording happens in
 * terminate() so it never delays the response, and it fails silently — the
 * moments this matters most (e.g. the database being down) must not cascade.
 */
class RecordRequestMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $isError = $response->getStatusCode() >= 500 ? 1 : 0;

            DB::table('system_request_metrics')->upsert(
                [[
                    'bucket_at' => now()->startOfHour()->toDateTimeString(),
                    'requests' => 1,
                    'server_errors' => $isError,
                ]],
                ['bucket_at'],
                [
                    'requests' => DB::raw('requests + 1'),
                    'server_errors' => DB::raw('server_errors + '.$isError),
                ],
            );
        } catch (\Throwable) {
            // Metrics are best-effort.
        }
    }
}
