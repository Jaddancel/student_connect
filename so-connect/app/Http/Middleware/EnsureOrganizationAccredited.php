<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\AccreditationService;
use App\Services\OrganizationAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks members whose organization(s) have all been accreditation-disabled:
 * they are redirected to a suspended notice until a super admin restores the
 * org (or the grace period elapses and it is purged). Admins/super admins
 * (user_type 1/2) are never blocked, and a member who still belongs to at least
 * one active org keeps normal access.
 */
class EnsureOrganizationAccredited
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && (int) $user->user_type === 3
            && ! $request->routeIs('org-suspended', 'logout')
        ) {
            $orgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

            if ($orgIds !== [] && $this->allDisabled($orgIds)) {
                return redirect()->route('org-suspended');
            }
        }

        return $next($request);
    }

    /**
     * @param  array<int,int>  $orgIds
     */
    private function allDisabled(array $orgIds): bool
    {
        $activeCount = Organization::query()
            ->whereIn('organization_id', $orgIds)
            ->where('accreditation_status', '!=', AccreditationService::STATUS_DISABLED)
            ->count();

        return $activeCount === 0;
    }
}
