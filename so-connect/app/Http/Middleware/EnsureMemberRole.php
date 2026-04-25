<?php

namespace App\Http\Middleware;

use App\Services\OrganizationAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Member category is reserved for member users only (not officer/president/superadmin).
        if ((int) $user->user_type !== 3) {
            abort(403, 'Only member users can access this page.');
        }

        $userId = (int) $user->getKey();
        $hasOfficerAccess = ! empty(OrganizationAuthorizationService::officerOrganizationIdsForUser($userId));
        $hasPresidentAccess = ! empty(OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId));

        if ($hasOfficerAccess || $hasPresidentAccess) {
            abort(403, 'Only member users can access this page.');
        }

        return $next($request);
    }
}
