<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficerOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('home');
        }

        if ((int) $user->user_type === 2) {
            return $next($request);
        }

        $isOfficerOrPresident = DB::table('organization_officers')
            ->where('user', (int) $user->getKey())
            ->whereIn('role', ['officer', 'president'])
            ->exists();

        if (! $isOfficerOrPresident) {
            abort(403, 'Only officers, presidents, and administrators can access this page.');
        }

        return $next($request);
    }
}
