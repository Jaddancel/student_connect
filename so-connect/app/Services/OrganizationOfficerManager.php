<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin (user type 2) reassignment of an organization's named officer
 * positions. A position is held by an `organization_officers` row with
 * role `president` (President) or role `officer` + matching `position`.
 * Replaced holders stay officers with position `Others`; a candidate who
 * already held another named position moves, leaving that one vacant.
 */
class OrganizationOfficerManager
{
    public const POSITIONS = ['President', 'Secretary', 'Auditor', 'Treasurer'];

    public const OTHER_POSITION = 'Others';

    /**
     * Accreditation-active organizations for the picker.
     *
     * @return Collection<int, object{organization_id:int, name:string}>
     */
    public function activeOrganizations(?int $organizationType = null): Collection
    {
        return DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.accreditation_status', '!=', AccreditationService::STATUS_DISABLED)
            ->when($organizationType, fn ($query) => $query->where('o.organization_type', $organizationType))
            ->orderBy('od.name')
            ->get(['o.organization_id', DB::raw("COALESCE(od.name, 'Unnamed Organization') as name")])
            ->map(fn ($row) => (object) [
                'organization_id' => (int) $row->organization_id,
                'name' => (string) $row->name,
            ]);
    }

    public function isActive(int $organizationId): bool
    {
        return DB::table('organizations')
            ->where('organization_id', $organizationId)
            ->where('accreditation_status', '!=', AccreditationService::STATUS_DISABLED)
            ->exists();
    }

    /**
     * Current holder of each named position plus the read-only "Other" officers.
     *
     * @return array{positions: array<int, array{position:string, holder:?array}>, others: array<int, array>}
     */
    public function board(int $organizationId): array
    {
        $officers = $this->rosterQuery($organizationId)
            ->whereIn('oo.role', ['president', 'officer'])
            ->orderByDesc('oo.reassigned_at')
            ->orderByDesc('oo.org_officer_id')
            ->get();

        $positions = [];
        $namedRowIds = [];

        foreach (self::POSITIONS as $position) {
            $holder = $officers->first(fn ($row) => self::holdsPosition($row, $position));
            if ($holder !== null) {
                $namedRowIds[] = (int) $holder->org_officer_id;
            }

            $positions[] = [
                'position' => $position,
                'holder' => $holder ? $this->present($holder) : null,
            ];
        }

        $others = $officers
            ->reject(fn ($row) => in_array((int) $row->org_officer_id, $namedRowIds, true))
            ->reject(fn ($row) => $this->namedPositionOf($row) !== null)
            ->unique('user')
            ->sortBy(fn ($row) => mb_strtolower(trim(($row->last_name ?? '').' '.($row->first_name ?? ''))))
            ->map(fn ($row) => $this->present($row))
            ->values()
            ->all();

        return ['positions' => $positions, 'others' => $others];
    }

    /**
     * Officer-account members of the organization matching the search term.
     *
     * @return Collection<int, array{user_id:int, name:string, email:string, current:string}>
     */
    public function searchCandidates(int $organizationId, string $term, int $limit = 10): Collection
    {
        $term = trim($term);

        $query = $this->rosterQuery($organizationId)
            ->where('u.user_type', User::TYPE_OFFICER);

        if ($term !== '') {
            $like = '%'.addcslashes($term, '\\%_').'%';
            $query->where(function ($q) use ($like) {
                $q->where('p.first_name', 'like', $like)
                    ->orWhere('p.last_name', 'like', $like)
                    ->orWhere('u.user_email', 'like', $like)
                    ->orWhereRaw("CONCAT_WS(' ', p.first_name, p.last_name) LIKE ?", [$like])
                    ->orWhereRaw("CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE ?", [$like]);
            });
        }

        return $query
            ->orderBy('p.last_name')
            ->orderBy('p.first_name')
            ->limit($limit * 3)
            ->get()
            ->groupBy('user')
            ->map(fn (Collection $rows) => $this->primaryRow($rows))
            ->take($limit)
            ->map(fn ($row) => [
                'user_id' => (int) $row->user,
                'name' => $this->displayName($row),
                'email' => (string) ($row->user_email ?? ''),
                'current' => $this->currentLabel($row),
            ])
            ->values();
    }

    /**
     * Give $position in the organization to $userId.
     *
     * @throws ValidationException
     */
    public function assign(int $organizationId, string $position, int $userId, ?int $actorId = null): void
    {
        $position = $this->normalizePosition($position);

        DB::transaction(function () use ($organizationId, $position, $userId, $actorId) {
            $organization = Organization::query()->lockForUpdate()->find($organizationId);

            if ($organization === null || $organization->isAccreditationDisabled()) {
                throw ValidationException::withMessages([
                    'organization' => 'This organization is not available for officer changes.',
                ]);
            }

            $rows = DB::table('organization_officers as oo')
                ->leftJoin('users as u', 'u.user_id', '=', 'oo.user')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->where('oo.organization', $organizationId)
                ->lockForUpdate()
                ->get([
                    'oo.org_officer_id', 'oo.user', 'oo.role', 'oo.position',
                    'u.user_type', 'u.user_email', 'p.first_name', 'p.middle_name', 'p.last_name',
                ]);

            $candidateRows = $rows->where('user', $userId);

            if ($userId <= 0 || $candidateRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'user_id' => 'The selected person is not a member of this organization.',
                ]);
            }

            $candidate = $this->primaryRow($candidateRows);

            if ((int) $candidate->user_type !== User::TYPE_OFFICER) {
                throw ValidationException::withMessages([
                    'user_id' => 'Only officer accounts can be assigned to an officer position.',
                ]);
            }

            if (self::holdsPosition($candidate, $position)) {
                throw ValidationException::withMessages([
                    'user_id' => $this->displayName($candidate).' is already the '.$position.'.',
                ]);
            }

            $previousHolders = $rows
                ->filter(fn ($row) => self::holdsPosition($row, $position))
                ->reject(fn ($row) => (int) $row->user === $userId);

            $now = now();

            if ($previousHolders->isNotEmpty()) {
                DB::table('organization_officers')
                    ->whereIn('org_officer_id', $previousHolders->pluck('org_officer_id')->all())
                    ->update(['role' => 'officer', 'position' => self::OTHER_POSITION, 'reassigned_at' => $now]);
            }

            DB::table('organization_officers')
                ->where('org_officer_id', $candidate->org_officer_id)
                ->update([
                    'role' => $position === 'President' ? 'president' : 'officer',
                    'position' => $position,
                    'reassigned_at' => $now,
                ]);

            // Stale `presidents` rows would otherwise make ExpectedSignatories
            // fall back to a former president.
            $nonPresidentRowIds = DB::table('organization_officers')
                ->where('organization', $organizationId)
                ->where('role', '!=', 'president')
                ->pluck('org_officer_id');

            if ($nonPresidentRowIds->isNotEmpty()) {
                DB::table('presidents')->whereIn('officer', $nonPresidentRowIds->all())->delete();
            }

            $orgName = (string) (DB::table('organization_details')
                ->where('organization_detail_id', $organization->detail)
                ->value('name') ?? 'Organization #'.$organizationId);

            ActionLogger::log(
                ActionLogger::CATEGORY_ORGANIZATION_OFFICERS,
                'officer_changed',
                sprintf('Assigned %s as %s of %s', $this->displayName($candidate), $position, $orgName),
                [
                    'organization_id' => $organizationId,
                    'position' => $position,
                    'user_id' => $userId,
                    'previous_role' => (string) $candidate->role,
                    'previous_position' => $candidate->position,
                    'replaced_user_ids' => $previousHolders->pluck('user')->map(fn ($id) => (int) $id)->values()->all(),
                ],
                $organization,
                $actorId,
            );
        });
    }

    public static function holdsPosition(object $row, string $position): bool
    {
        $role = mb_strtolower((string) $row->role);

        if (mb_strtolower($position) === 'president') {
            return $role === 'president';
        }

        return $role === 'officer'
            && mb_strtolower(trim((string) $row->position)) === mb_strtolower($position);
    }

    private function namedPositionOf(object $row): ?string
    {
        foreach (self::POSITIONS as $position) {
            if (self::holdsPosition($row, $position)) {
                return $position;
            }
        }

        return null;
    }

    private function normalizePosition(string $position): string
    {
        foreach (self::POSITIONS as $known) {
            if (strcasecmp($known, trim($position)) === 0) {
                return $known;
            }
        }

        throw ValidationException::withMessages(['position' => 'Unknown officer position.']);
    }

    private function rosterQuery(int $organizationId)
    {
        return DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('oo.organization', $organizationId)
            ->select([
                'oo.org_officer_id', 'oo.user', 'oo.role', 'oo.position', 'oo.reassigned_at',
                'u.user_email', 'u.user_type', 'p.first_name', 'p.middle_name', 'p.last_name',
            ]);
    }

    /**
     * A user may have both a member and an officer row; prefer the highest role.
     */
    private function primaryRow(Collection $rows): object
    {
        $rank = ['president' => 0, 'officer' => 1];

        return $rows
            ->sortBy(fn ($row) => [$rank[mb_strtolower((string) $row->role)] ?? 2, -(int) $row->org_officer_id])
            ->first();
    }

    private function present(object $row): array
    {
        return [
            'org_officer_id' => (int) $row->org_officer_id,
            'user_id' => (int) $row->user,
            'name' => $this->displayName($row),
            'email' => (string) ($row->user_email ?? ''),
        ];
    }

    private function displayName(object $row): string
    {
        $name = trim(implode(' ', array_filter([$row->first_name ?? null, $row->last_name ?? null])));

        return $name !== '' ? $name : ((string) ($row->user_email ?? '') ?: 'Unnamed member');
    }

    private function currentLabel(object $row): string
    {
        $named = $this->namedPositionOf($row);
        if ($named !== null) {
            return $named;
        }

        return mb_strtolower((string) $row->role) === 'officer' ? 'Officer' : 'Member';
    }
}
