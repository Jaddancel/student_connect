<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Organization;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipRegistrationController extends Controller
{
    public function create()
    {
        $organizations = Organization::query()
            ->leftJoin('organization_details as details', 'details.organization_detail_id', '=', 'organizations.detail')
            ->where('organizations.organization_type', '!=', 6)
            ->orderBy('organizations.organization_type')
            ->orderBy('details.name')
            ->get([
                'organizations.organization_id',
                'organizations.organization_type',
                'details.name as organization_name',
            ])
            ->groupBy('organization_type');

        return view('pages.organizations.register', [
            'title' => 'Membership Registration',
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request, \App\Services\RequestTypeService $requestTypeService)
    {
        $validated = $request->validate([
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'organization_id')->where(function ($query) {
                    $query->where('organization_type', '!=', 6);
                }),
            ],
        ]);

        $userId = (int) $request->user()->getKey();
        $organizationId = (int) $validated['organization_id'];

        $isAlreadyMember = \Illuminate\Support\Facades\DB::table('organization_officers')
            ->where('user', $userId)
            ->where('organization', $organizationId)
            ->exists();

        if ($isAlreadyMember) {
            return back()
                ->withErrors(['organization_id' => 'You are already a member of this organization.'])
                ->withInput();
        }

        $actionValue = $organizationId.'|'.$userId;

        $hasPendingRequest = ActionRequest::query()
            ->where('action_type', 1)
            ->where('action', $actionValue)
            ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
            ->exists();

        if ($hasPendingRequest) {
            return back()->with('status', 'You already have a pending membership request for this organization.');
        }

        $requestType = $requestTypeService->resolveSystemType(
            RequestType::SYSTEM_KEY_MEMBERSHIP,
            'Membership Request',
            RequestType::CATEGORY_ORGANIZATION,
            $userId,
        );

        $actionRequest = ActionRequest::create([
            'action' => $actionValue,
            'action_type' => 1,
            'request_type_id' => (int) $requestType->getKey(),
            'organization_id' => $organizationId,
            'requested_by' => $userId,
            'payload' => [
                'organization_id' => $organizationId,
                'user_id' => $userId,
            ],
            'user' => $userId,
            'requested_at' => now(),
        ]);

        app(\App\Services\RequestApprovalService::class)->autoApproveIfPresident($actionRequest, $userId);

        return back()->with('success', 'Membership request submitted successfully.');
    }
}
