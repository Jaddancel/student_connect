<?php

namespace App\Support;

use App\Models\Officer;
use App\Models\Organization;
use App\Models\User;

/**
 * Resolves the organization-scoped universal fields (`org_name`,
 * `org_president`, `org_auditor`, `org_secretary`, `adviser`) that
 * {@see UniversalField} declares but cannot read off a profile. Values come
 * from the submitter's organization: its registered name for `org_name`,
 * current officeholders for the role fields, and the extensible adviser list
 * for `adviser`.
 */
final class OrganizationField
{
    /**
     * The organization to resolve org fields against for a user: their most
     * recent officer assignment's organization. Null for users with no officer
     * row (e.g. plain admins), in which case org fields render empty.
     */
    public static function resolveOrganization(?User $user): ?Organization
    {
        if ($user === null) {
            return null;
        }

        $officer = $user->officers()
            ->orderByDesc('org_officer_id')
            ->first();

        return $officer?->organization()->first();
    }

    /**
     * Scalar value for an org role field. `adviser` has no single value (it's a
     * per-submission choice), so it returns null here.
     */
    public static function value(?Organization $organization, string $key): ?string
    {
        if ($organization === null) {
            return null;
        }

        if ($key === 'org_name') {
            // `detail` is a FK column that shadows the relation — query it.
            $name = $organization->detail()->first()?->name;

            return trim((string) $name) !== '' ? $name : null;
        }

        if ($key === 'org_category') {
            $type = $organization->organization_type;

            return $type !== null && $type !== ''
                ? \App\Enums\OrganizationType::label((int) $type)
                : null;
        }

        $officer = match ($key) {
            'org_president' => self::officerByRole($organization, 'president'),
            'org_auditor' => self::officerByPosition($organization, 'auditor'),
            'org_secretary' => self::officerByPosition($organization, 'secretary'),
            default => null,
        };

        return $officer ? self::officerName($officer) : null;
    }

    /**
     * The org's known adviser names, alphabetical — the option list for the
     * "Advisers" dropdown.
     *
     * @return string[]
     */
    public static function advisers(?Organization $organization): array
    {
        if ($organization === null) {
            return [];
        }

        return $organization->advisers()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Upsert a newly-typed adviser name into the org's list (no-op on blank or
     * when the org already has it). Called when a submission fills an adviser
     * field so the value is offered next time.
     */
    public static function rememberAdviser(?Organization $organization, ?string $name): void
    {
        $name = trim((string) $name);
        if ($organization === null || $name === '') {
            return;
        }

        $organization->advisers()->firstOrCreate(['name' => $name]);
    }

    private static function officerByRole(Organization $organization, string $role): ?Officer
    {
        return $organization->officersOfThisOrganization()
            ->whereRaw('LOWER(role) = ?', [$role])
            ->orderByDesc('org_officer_id')
            ->first();
    }

    private static function officerByPosition(Organization $organization, string $position): ?Officer
    {
        return $organization->officersOfThisOrganization()
            ->whereRaw('LOWER(position) = ?', [$position])
            ->orderByDesc('org_officer_id')
            ->first();
    }

    private static function officerName(Officer $officer): ?string
    {
        // `user` is a FK column that shadows the relation, so load it explicitly.
        $profile = $officer->user()->first()?->profile()->first();
        if ($profile === null) {
            return null;
        }

        $parts = array_filter([
            $profile->first_name,
            $profile->middle_name,
            $profile->last_name,
        ], static fn ($p) => trim((string) $p) !== '');

        return $parts === [] ? null : implode(' ', $parts);
    }
}
