<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrganizationAuthorizationService
{
    /**
     * @return array<int>
     */
    public static function officerOrganizationIdsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return DB::table('members as m')
            ->join('organization_officers as oo', function ($join) {
                $join->on('oo.member', '=', 'm.member_id')
                    ->on('oo.organization', '=', 'm.organization');
            })
            ->where('m.user', $userId)
            ->whereIn('oo.role', ['officer', 'president'])
            ->pluck('m.organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int>
     */
    public static function presidentOrganizationIdsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return DB::table('members as m')
            ->join('organization_officers as oo', function ($join) {
                $join->on('oo.member', '=', 'm.member_id')
                    ->on('oo.organization', '=', 'm.organization');
            })
            ->where('m.user', $userId)
            ->where('oo.role', 'president')
            ->pluck('m.organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values()
            ->all();
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
