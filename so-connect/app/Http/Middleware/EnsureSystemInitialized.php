<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemInitialized
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $hasSuperadmin = User::where('user_type', 1)->exists();
        } catch (\Throwable $e) {
            // DB not migrated yet / unreachable — don't mask the real error, let it through.
            return $next($request);
        }

        $onSetupRoute = $request->routeIs('setup.create', 'setup.store');

        if (! $hasSuperadmin && ! $onSetupRoute) {
            return redirect()->route('setup.create');
        }

        if ($hasSuperadmin && $onSetupRoute) {
            return redirect()->route('home'); // setup is one-time only
        }

        return $next($request);
    }
}
