<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsurePresidentOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ((int) $user->user_type === 2) {
            return $next($request);
        }

        $isPresident = DB::table('organization_officers as oo')
            ->join('members as m', 'm.member_id', '=', 'oo.member')
            ->where('m.user', (int) $user->getKey())
            ->where('oo.role', 'president')
            ->exists();

        if (! $isPresident) {
            abort(403, 'Only presidents and administrators can access this page.');
        }

        return $next($request);
    }
}
