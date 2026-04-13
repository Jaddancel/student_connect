<?php

namespace App\Helpers;

use App\Models\Member;

class OrganizationHelper
{
    public function returnTheOrganizationsTheUserIsAMemberOf($userId): array
    {
        if ($userId === null) {
            return [];
        }

        return Member::query()
            ->where('user', $userId)
            ->whereNotNull('organization')
            ->pluck('organization')
            ->map(static fn ($organizationId) => (int) $organizationId)
            ->unique()
            ->values()
            ->all();
    }

    public function regsiter() {}
}
