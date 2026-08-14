<?php

namespace App\Forms;

use App\Models\Officer;
use App\Models\Organization;
use App\Models\President;
use App\Models\Profile;
use App\Models\User;
use App\Services\SignatureReferenceService;
use App\Support\OrganizationField;
use Illuminate\Support\Collection;

/**
 * Resolves the expected signer(s) declared on a signature field into the
 * {@see \App\Models\SignatureReference} set a submitted signature must match in
 * Compare mode.
 *
 * Two sources combine: explicitly-picked profiles, and organization positions
 * (President/Treasurer/…) resolved against the *submitting* organization's
 * current officers — so "the President must sign" means org A's president for an
 * org-A submission and org B's for an org-B one. Profiles without a signature on
 * file are skipped (there is nothing to compare against).
 */
class ExpectedSignatories
{
    public function __construct(private readonly SignatureReferenceService $references) {}

    /**
     * The registry references to compare a submission's signature against for a
     * signature field. Empty when no expected signer resolves to a signature on
     * file (the caller treats that as fail-closed in Compare mode).
     *
     * @param  array<string,mixed>  $options  the field's `field_options`
     * @return Collection<int, \App\Models\SignatureReference>
     */
    public function resolve(array $options, ?User $user): Collection
    {
        $expected = FieldType::signatureExpected($options);

        /** @var Collection<int, Profile> $profiles */
        $profiles = collect();

        foreach ($expected['profiles'] as $profileId) {
            $profile = Profile::query()->find($profileId);
            if ($profile !== null) {
                $profiles->push($profile);
            }
        }

        if ($expected['positions'] !== []) {
            $organization = OrganizationField::resolveOrganization($user);
            if ($organization !== null) {
                foreach ($expected['positions'] as $position) {
                    $profile = $this->profileForPosition($organization, $position);
                    if ($profile !== null) {
                        $profiles->push($profile);
                    }
                }
            }
        }

        // Sync each profile into the registry and keep only those that yielded a
        // reference (syncFromProfile returns null when the profile has no
        // signature). Dedupe by profile so a person expected twice compares once.
        return $profiles
            ->unique(fn (Profile $profile) => (int) $profile->profile_id)
            ->map(fn (Profile $profile) => $this->references->syncFromProfile($profile))
            ->filter()
            ->values();
    }

    /**
     * The profile of the org's current holder of a position. Matches either the
     * officer `role` or `position` column case-insensitively; the President may
     * additionally be recorded only in the `presidents` satellite table.
     */
    private function profileForPosition(Organization $organization, string $position): ?Profile
    {
        $needle = mb_strtolower($position);

        $officer = $organization->officersOfThisOrganization()
            ->where(function ($query) use ($needle) {
                $query->whereRaw('LOWER(role) = ?', [$needle])
                    ->orWhereRaw('LOWER(position) = ?', [$needle]);
            })
            ->orderByDesc('org_officer_id')
            ->first();

        if ($officer === null && $needle === 'president') {
            $officer = $this->presidentOfficer($organization);
        }

        if ($officer === null) {
            return null;
        }

        // `user` is a FK column that shadows the relation, so load it explicitly.
        return $officer->user()->first()?->profile()->first();
    }

    private function presidentOfficer(Organization $organization): ?Officer
    {
        $officerIds = $organization->officersOfThisOrganization()->pluck('org_officer_id');
        if ($officerIds->isEmpty()) {
            return null;
        }

        $president = President::query()
            ->whereIn('officer', $officerIds)
            ->orderByDesc('president_id')
            ->first();

        return $president?->officer()->first();
    }
}
