<?php

namespace App\Http\Middleware;

use App\Services\OrganizationAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePresidentRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ((int) $user->user_type === 1) {
            abort(403, 'President pages are reserved for organization presidents.');
        }

        $authorizedOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey());

        if (empty($authorizedOrganizationIds)) {
            abort(403, 'President pages are reserved for organization presidents.');
        }

        return $next($request);
    }
}
