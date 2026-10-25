<?php

namespace App\Http\Controllers;

use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function switch(Request $request, int $organization)
    {
        $userId = (int) $request->user()->getKey();
        $allowedOrganizationIds = DB::table('organization_officers')
            ->where('user', $userId)
            ->pluck('organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values()
            ->all();

        if (! in_array($organization, $allowedOrganizationIds, true)) {
            abort(403);
        }

        if (! in_array($organization, OrganizationAuthorizationService::selectableOrganizationIdsForUser($userId), true)) {
            abort(403, 'This organization is suspended for missing its accreditation requirements.');
        }

        session(['active_organization_id' => $organization]);

        return redirect()->back();
    }

    public function manage(Request $request)
    {
        $userId = (int) $request->user()->getKey();
        $queryText = trim((string) $request->query('q', ''));
        $selectedRole = in_array((string) $request->query('role'), ['president', 'officer', 'member'], true)
            ? (string) $request->query('role')
            : 'all';

        $organizations = DB::table('organization_officers as oo')
            ->join('organizations as o', 'o.organization_id', '=', 'oo.organization')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('oo.user', $userId)
            ->whereIn('oo.role', ['officer', 'president'])
            ->select([
                'o.organization_id',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
            ])
            ->selectRaw("CASE
                WHEN SUM(CASE WHEN oo.role = 'president' THEN 1 ELSE 0 END) > 0 THEN 'president'
                ELSE 'officer'
            END as role_name")
            ->groupBy('o.organization_id', 'od.name')
            ->orderBy('organization_name')
            ->get();

        $allowedOrganizationIds = $organizations
            ->pluck('organization_id')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->all();

        $selectedOrganizationId = (int) $request->query('organization_id', 0);

        if (! in_array($selectedOrganizationId, $allowedOrganizationIds, true)) {
            $selectedOrganizationId = (int) ($allowedOrganizationIds[0] ?? 0);
        }

        $members = collect();
        $memberCounts = ['all' => 0, 'president' => 0, 'officer' => 0, 'member' => 0];

        if ($selectedOrganizationId > 0) {
            $allMembers = DB::table('organization_officers as oo')
                ->leftJoin('users as u', 'u.user_id', '=', 'oo.user')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->where('oo.organization', $selectedOrganizationId)
                ->select([
                    'oo.org_officer_id',
                    'oo.user as user_id',
                    'oo.role',
                    'oo.position',
                    'u.user_email',
                    'p.first_name',
                    'p.middle_name',
                    'p.last_name',
                    'p.student_id',
                    'p.course',
                    'p.course_year',
                    'p.college',
                    'oo.member_since',
                ])
                ->selectRaw("CASE
                    WHEN oo.role = 'president' THEN 'President'
                    WHEN oo.role = 'officer' THEN 'Officer'
                    ELSE 'Member'
                END as membership_role")
                ->selectRaw("CASE
                    WHEN oo.role = 'president' THEN 1
                    WHEN oo.role = 'officer' THEN 2
                    ELSE 3
                END as role_rank")
                ->orderBy('role_rank')
                ->orderByRaw('oo.member_since IS NULL')
                ->orderByDesc('oo.member_since')
                ->orderBy('p.last_name')
                ->orderBy('p.first_name')
                ->get()
                ->unique(fn ($member) => $member->user_id
                    ? 'user-'.(int) $member->user_id
                    : 'membership-'.(int) $member->org_officer_id)
                ->map(function ($member) {
                    $name = trim(implode(' ', array_filter([
                        $member->first_name,
                        $member->middle_name,
                        $member->last_name,
                    ])));
                    $member->display_name = $name !== '' ? $name : 'Unknown Member';
                    $member->role_key = in_array($member->role, ['president', 'officer'], true)
                        ? $member->role
                        : 'member';
                    $member->initials = collect(preg_split('/\s+/', $member->display_name) ?: [])
                        ->filter()
                        ->take(2)
                        ->map(fn ($part) => mb_strtoupper(mb_substr((string) $part, 0, 1)))
                        ->implode('');

                    return $member;
                })
                ->values();

            $memberCounts = [
                'all' => $allMembers->count(),
                'president' => $allMembers->where('role_key', 'president')->count(),
                'officer' => $allMembers->where('role_key', 'officer')->count(),
                'member' => $allMembers->where('role_key', 'member')->count(),
            ];

            $members = $allMembers
                ->when($selectedRole !== 'all', fn ($rows) => $rows->where('role_key', $selectedRole))
                ->when($queryText !== '', function ($rows) use ($queryText) {
                    $needle = mb_strtolower($queryText);

                    return $rows->filter(function ($member) use ($needle) {
                        $searchable = mb_strtolower(implode(' ', array_filter([
                            $member->display_name,
                            $member->user_email,
                            $member->student_id,
                            $member->course,
                            $member->course_year,
                            $member->college,
                            $member->position,
                            $member->membership_role,
                        ])));

                        return str_contains($searchable, $needle);
                    });
                })
                ->values();
        }

        return view('pages.organizations.manage', [
            'title' => 'Organization Members',
            'organizations' => $organizations,
            'selectedOrganizationId' => $selectedOrganizationId,
            'members' => $members,
            'memberCounts' => $memberCounts,
            'queryText' => $queryText,
            'selectedRole' => $selectedRole,
        ]);
    }
}
