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
            // A superadmin who has not confirmed their email cannot sign in, so
            // the system is only really initialized once one is *verified*.
            // Keying on mere existence would let a mistyped address lock the
            // deployment out of both setup and login permanently.
            $hasSuperadmin = User::where('user_type', User::TYPE_SUPERADMIN)
                ->whereNotNull('email_verified_at')
                ->exists();
        } catch (\Throwable $e) {
            // DB not migrated yet / unreachable — don't mask the real error, let it through.
            return $next($request);
        }

        $onSetupRoute = $request->routeIs('setup.*');

        if (! $hasSuperadmin && ! $onSetupRoute) {
            return redirect()->route('setup.create');
        }

        if ($hasSuperadmin && $onSetupRoute) {
            return redirect()->route('home'); // setup is one-time only
        }

        return $next($request);
    }
}
