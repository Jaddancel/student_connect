<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs a successful document download/generation (route middleware
 * `track.download:<kind>`) for the System Dashboard activity chart. Redirects
 * and error responses (e.g. a missing file sending the user back) aren't counted.
 */
class TrackDocumentDownload
{
    public function handle(Request $request, Closure $next, string $kind = 'document'): Response
    {
        $response = $next($request);

        if ($response->isSuccessful()) {
            try {
                DB::table('document_downloads')->insert([
                    'user_id' => $request->user()?->getKey(),
                    'kind' => $kind,
                    'route_name' => $request->route()?->getName(),
                    'downloaded_at' => now(),
                ]);
            } catch (\Throwable) {
                // Tracking is best-effort; never block the download.
            }
        }

        return $response;
    }
}
