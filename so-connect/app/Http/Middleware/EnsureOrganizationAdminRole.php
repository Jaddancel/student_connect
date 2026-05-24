<?php

namespace App\Http\Middleware;

use App\Services\OrganizationAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('home');
        }

        if ((int) $user->user_type <= 2) {
            abort(403, 'Organization admin pages are reserved for officers and presidents.');
        }

        $authorizedOrganizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

        if (empty($authorizedOrganizationIds)) {
            abort(403, 'Organization admin pages are reserved for officers and presidents.');
        }

        return $next($request);
    }
}
