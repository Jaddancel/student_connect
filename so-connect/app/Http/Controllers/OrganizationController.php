<?php

namespace App\Http\Controllers;

use App\Models\Member;

class OrganizationController extends Controller
{
    // Organization list will return the organizations that the user is a part of, their role, and their approval status.
    // Approval status returns pending when the approval_id is null
    // This will show up on my_organizations page
    public function user_organizations()
    {
        $user = auth()->user();

        $members = Member::where('user', $user->user_id)
            ->with([
                'memberDetail.role',
                'memberDetail.organization.organization',
                'approval',
            ])
            ->get();

        $organizations = $members->map(function ($member) {
            $detail = $member->memberDetail;
            $org = $detail?->organization?->organization;

            return (object) [
                'organization_name' => $org?->organization_name ?? 'Unknown',
                'organization_initials' => $org?->organization_initials ?? '',
                'role_name' => $detail?->role?->role_name ?? 'Member',
                'status' => is_null($member->approval_id) || is_null($member->approval) ? 'Pending' : 'Approved',
                'member_since' => $detail?->member_since,
            ];
        });

        return view('student.my_organization', compact('organizations'));
    }
}
