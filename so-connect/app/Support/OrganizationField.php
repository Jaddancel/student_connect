<?php

namespace App\Support;

use App\Models\Officer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the organization-scoped universal fields (`org_name`,
 * `org_president`, `org_auditor`, `org_secretary`, `adviser`) that
 * {@see UniversalField} declares but cannot read off a profile. Values come
 * from the submitter's organization: its registered name for `org_name`,
 * current officeholders for the role fields and their saved profile signatures,
 * and the extensible adviser list for `adviser`.
 */
final class OrganizationField
{
    /**
     * The organization a user acts for (forms are filed under it, and org
     * fields resolve against it):
     *
     *  1. the organization picked in the switcher, when it is the signed-in
     *     user's own session and they are an officer/president there;
     *  2. otherwise their most recent officer/president assignment;
     *  3. otherwise their most recent assignment of any role (e.g. `member`).
     *
     * A `member` row never outranks an officer role — joining another
     * organization as a member must not re-home the user's own requests.
     * Null for users with no officer row (e.g. plain admins), in which case
     * org fields render empty.
     */
    public static function resolveOrganization(?User $user): ?Organization
    {
        if ($user === null) {
            return null;
        }

        $officerRoles = fn () => $user->officers()->whereIn('role', ['officer', 'president']);

        if (Auth::check() && (int) Auth::id() === (int) $user->getKey()) {
            $activeOrganizationId = (int) session('active_organization_id', 0);
            if ($activeOrganizationId > 0 && $officerRoles()->where('organization', $activeOrganizationId)->exists()) {
                return Organization::query()->find($activeOrganizationId);
            }
        }

        $officer = $officerRoles()->orderByDesc('org_officer_id')->first()
            ?? $user->officers()->orderByDesc('org_officer_id')->first();

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

        $signaturePosition = match ($key) {
            'org_president_signature' => 'president',
            'org_treasurer_signature' => 'treasurer',
            'org_auditor_signature' => 'auditor',
            'org_secretary_signature' => 'secretary',
            default => null,
        };
        if ($signaturePosition !== null) {
            $officer = self::officerByPosition($organization, $signaturePosition, officersOnly: true);
            $path = $officer?->user()->first()?->profile()->first()?->signature_path;

            return trim((string) $path) !== '' ? $path : null;
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

    private static function officerByPosition(Organization $organization, string $position, bool $officersOnly = false): ?Officer
    {
        return $organization->officersOfThisOrganization()
            ->when($officersOnly, fn ($query) => $query->whereIn('role', ['officer', 'president']))
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
