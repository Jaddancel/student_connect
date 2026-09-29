<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizationAuthorizationService
{
    /**
     * @return array<int>
     */
    public static function officerOrganizationIdsForUser(int $userId): array
    {
        return self::scopeToActiveOrganization($userId, self::allOfficerOrganizationIdsForUser($userId));
    }

    /**
     * @return array<int>
     */
    public static function presidentOrganizationIdsForUser(int $userId): array
    {
        return self::scopeToActiveOrganization($userId, self::allPresidentOrganizationIdsForUser($userId));
    }

    /**
     * Every officer/president org, ignoring the switcher — for business logic
     * (enrollment, auto-approval) that reasons about a user's real roles.
     *
     * @return array<int>
     */
    public static function allOfficerOrganizationIdsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return DB::table('organization_officers')
            ->where('user', $userId)
            ->whereIn('role', ['officer', 'president'])
            ->pluck('organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int>
     */
    public static function allPresidentOrganizationIdsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return DB::table('organization_officers')
            ->where('user', $userId)
            ->where('role', 'president')
            ->pluck('organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Narrow a role-derived org set to the org the current user selected in the
     * switcher, so switching context re-scopes every feature to that one org
     * (and an org where they're only a member yields an empty officer/president
     * set). Only applies to the authenticated UI user with an active org set;
     * background and cross-user callers see the full set.
     *
     * @param  array<int>  $organizationIds
     * @return array<int>
     */
    private static function scopeToActiveOrganization(int $userId, array $organizationIds): array
    {
        if (! Auth::check() || (int) Auth::id() !== $userId) {
            return $organizationIds;
        }

        $activeOrganizationId = (int) session('active_organization_id', 0);
        if ($activeOrganizationId <= 0) {
            return $organizationIds;
        }

        return in_array($activeOrganizationId, $organizationIds, true) ? [$activeOrganizationId] : [];
    }

    public static function extractOrganizationIdFromAction(?string $action): ?int
    {
        $firstSegment = trim((string) explode('|', (string) $action, 2)[0]);
        $organizationId = (int) $firstSegment;

        if ($organizationId <= 0 || (string) $organizationId !== $firstSegment) {
            return null;
        }

        return $organizationId;
    }
}
