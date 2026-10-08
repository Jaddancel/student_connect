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
 *
 * The check covers every officer org, not just the switcher's selection, and
 * the selection is moved off a disabled org before the request is handled, so
 * access no longer depends on which org the switcher happened to pick.
 */
class EnsureOrganizationAccredited
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $request->routeIs('org-suspended', 'logout')) {
            $userId = (int) $user->getKey();

            if ((int) $user->user_type === 3) {
                $orgIds = OrganizationAuthorizationService::allOfficerOrganizationIdsForUser($userId);

                if ($orgIds !== [] && $this->allDisabled($orgIds)) {
                    return redirect()->route('org-suspended');
                }
            }

            $this->keepSelectionOnActiveOrganization($request, $userId);
        }

        return $next($request);
    }

    private function keepSelectionOnActiveOrganization(Request $request, int $userId): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $selectable = OrganizationAuthorizationService::selectableOrganizationIdsForUser($userId);
        $selected = (int) $request->session()->get('active_organization_id', 0);

        if ($selectable !== [] && ! in_array($selected, $selectable, true)) {
            $request->session()->put('active_organization_id', $selectable[0]);
        }
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
