<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function manage(Request $request)
    {
        $userId = (int) $request->user()->getKey();

        $organizations = DB::table('members as m')
            ->join('organization_officers as oo', function ($join) {
                $join->on('oo.member', '=', 'm.member_id')
                    ->on('oo.organization', '=', 'm.organization');
            })
            ->join('organizations as o', 'o.organization_id', '=', 'm.organization')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('m.user', $userId)
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
            $members = DB::table('members as m')
                ->leftJoin('users as u', 'u.user_id', '=', 'm.user')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->leftJoin('organization_officers as oo', function ($join) {
                    $join->on('oo.member', '=', 'm.member_id')
                        ->on('oo.organization', '=', 'm.organization');
                })
                ->where('m.organization', $selectedOrganizationId)
                ->select([
                    'm.member_id',
                    'u.user_email',
                    'p.first_name',
                    'p.middle_name',
                    'p.last_name',
                    'm.member_since',
                ])
                ->selectRaw("CASE
                    WHEN SUM(CASE WHEN oo.role = 'president' THEN 1 ELSE 0 END) > 0 THEN 'President'
                    WHEN SUM(CASE WHEN oo.role = 'officer' THEN 1 ELSE 0 END) > 0 THEN 'Officer'
                    ELSE 'Member'
                END as membership_role")
                ->selectRaw("CASE
                    WHEN SUM(CASE WHEN oo.role = 'president' THEN 1 ELSE 0 END) > 0 THEN 1
                    WHEN SUM(CASE WHEN oo.role = 'officer' THEN 1 ELSE 0 END) > 0 THEN 2
                    ELSE 3
                END as role_rank")
                ->groupBy(
                    'm.member_id',
                    'u.user_email',
                    'p.first_name',
                    'p.middle_name',
                    'p.last_name',
                    'm.member_since'
                )
                ->orderBy('role_rank')
                ->orderByRaw('m.member_since IS NULL')
                ->orderByDesc('m.member_since')
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
