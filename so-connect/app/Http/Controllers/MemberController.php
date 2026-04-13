<?php

namespace App\Http\Controllers;

use App\Helpers\OrganizationHelper;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    public function registration_form($user_id)
    {
        $joinedOrganizationIds = (new OrganizationHelper())->returnTheOrganizationsTheUserIsAMemberOf($user_id);

        $organizations = DB::table('organizations')
            ->leftJoin('organization_details', 'organizations.detail', '=', 'organization_details.organization_detail_id')
            ->when(! empty($joinedOrganizationIds), function ($query) use ($joinedOrganizationIds) {
                $query->whereNotIn('organizations.organization_id', $joinedOrganizationIds);
            })
            ->select([
                'organizations.organization_id',
                'organizations.organization_type',
                DB::raw("COALESCE(organization_details.name, CONCAT('Organization #', organizations.organization_id)) as organization_name"),
            ])
            ->orderBy('organization_name')
            ->get();

        $organizationsByType = [];

        foreach ($organizations as $organization) {
            $typeKey = (string) $organization->organization_type;

            if (! isset($organizationsByType[$typeKey])) {
                $organizationsByType[$typeKey] = [];
            }

            $organizationsByType[$typeKey][] = [
                'organization_id' => (int) $organization->organization_id,
                'organization_name' => (string) $organization->organization_name,
            ];
        }

        return view('pages.form.membership_registration', [
            'title' => 'Membership Registration',
            'organizationsByType' => $organizationsByType,
        ]);
    }
}
