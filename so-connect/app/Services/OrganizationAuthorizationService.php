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
    public static function presidentOrganizationIdsForUser(int $userId): array
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
