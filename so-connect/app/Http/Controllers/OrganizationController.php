<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function switch(Request $request, int $organization)
    {
        $userId = (int) $request->user()->getKey();
        $allowedOrganizationIds = DB::table('organization_officers')
            ->where('user', $userId)
            ->whereIn('role', ['officer', 'president'])
            ->pluck('organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values()
            ->all();

        if (! in_array($organization, $allowedOrganizationIds, true)) {
            abort(403);
        }

        session(['active_organization_id' => $organization]);

        return redirect()->back();
    }

    public function manage(Request $request)
    {
        $userId = (int) $request->user()->getKey();

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

        if ($selectedOrganizationId > 0) {
            $members = DB::table('organization_officers as oo')
                ->leftJoin('users as u', 'u.user_id', '=', 'oo.user')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->where('oo.organization', $selectedOrganizationId)
                ->select([
                    'oo.org_officer_id',
                    'u.user_email',
                    'p.first_name',
                    'p.middle_name',
                    'p.last_name',
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
                ->get();
        }

        return view('pages.organizations.manage', [
            'title' => 'Manage Organization',
            'organizations' => $organizations,
            'selectedOrganizationId' => $selectedOrganizationId,
            'members' => $members,
        ]);
    }
}
