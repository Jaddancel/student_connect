<?php

namespace App\Http\Middleware;

use App\Services\OrganizationAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePresidentOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('home');
        }

        if ($user->isAdministrative()) {
            return $next($request);
        }

        $isPresident = ! empty(OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey()));

        if (! $isPresident) {
            abort(403, 'Only presidents and administrators can access this page.');
        }

        return $next($request);
    }
}
