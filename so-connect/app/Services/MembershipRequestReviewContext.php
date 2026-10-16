<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MembershipRequestReviewContext
{
    /**
     * @param  array<int,array{organization_id:int,user_id:int,requested_role?:string}>  $requests
     * @return array<int,array{organization_name:string,requested_role:string,current_roles:array<int,array{organization_name:string,role:string}>,officer_roles:array<int,array{role:string,count:int,names:array<int,string>}>}>
     */
    public function forRequests(array $requests): array
    {
        $organizationIds = collect($requests)
            ->pluck('organization_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $userIds = collect($requests)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($organizationIds->isEmpty() && $userIds->isEmpty()) {
            return [];
        }

        $receivingOrganizations = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->leftJoin('organization_officers as oo', 'oo.organization', '=', 'o.organization_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('o.organization_id', $organizationIds)
            ->orderBy('p.last_name')
            ->orderBy('p.first_name')
            ->get([
                'o.organization_id',
                'od.name as organization_name',
                'oo.user as user_id',
                'oo.role',
                'oo.position',
                'p.first_name',
                'p.middle_name',
                'p.last_name',
            ])
            ->groupBy('organization_id');

        $requesterOrganizations = DB::table('organization_officers as oo')
            ->join('organizations as o', 'o.organization_id', '=', 'oo.organization')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('oo.user', $userIds)
            ->orderBy('od.name')
            ->get([
                'oo.user as user_id',
                'oo.role',
                'oo.position',
                'od.name as organization_name',
            ])
            ->groupBy('user_id');

        $context = [];

        foreach ($requests as $requestId => $request) {
            $organizationId = (int) ($request['organization_id'] ?? 0);
            $requesterId = (int) ($request['user_id'] ?? 0);
            $rows = $receivingOrganizations->get($organizationId, collect());
            $officers = $rows
                ->filter(fn ($row) => in_array((string) $row->role, ['president', 'officer'], true))
                ->unique('user_id')
                ->map(fn ($row) => [
                    'name' => $this->fullName($row) ?: 'Officer #'.$row->user_id,
                    'role' => (string) $row->role === 'president'
                        ? 'President'
                        : (trim((string) $row->position) !== '' ? trim((string) $row->position) : 'Others'),
                ])
                ->groupBy('role')
                ->sortKeysUsing(fn (string $left, string $right) => $this->positionOrder($left) <=> $this->positionOrder($right))
                ->map(fn ($group, $role) => [
                    'role' => $role,
                    'count' => $group->count(),
                    'names' => $group->pluck('name')->all(),
                ])
                ->values()
                ->all();

            $currentRoles = $requesterOrganizations->get($requesterId, collect())
                ->map(fn ($row) => [
                    'organization_name' => (string) ($row->organization_name ?? 'Unknown Organization'),
                    'role' => ucfirst((string) $row->role).(trim((string) $row->position) !== '' ? ' · '.trim((string) $row->position) : ''),
                ])
                ->values()
                ->all();

            $context[$requestId] = [
                'organization_name' => (string) ($rows->first()->organization_name ?? 'Unknown Organization'),
                'requested_role' => trim((string) ($request['requested_role'] ?? '')) ?: 'Not specified',
                'current_roles' => $currentRoles,
                'officer_roles' => $officers,
            ];
        }

        return $context;
    }

    private function fullName(object $row): string
    {
        return trim(implode(' ', array_filter([
            $row->first_name,
            $row->middle_name,
            $row->last_name,
        ], fn ($part) => trim((string) $part) !== '')));
    }

    private function positionOrder(string $position): int
    {
        return match (strtolower($position)) {
            'president' => 0,
            'secretary' => 1,
            'auditor' => 2,
            'treasurer' => 3,
            'others' => 4,
            default => 4,
        };
    }
}
