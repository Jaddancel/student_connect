<?php

namespace App\Http\Controllers;

use App\Models\ActionRequest;
use App\Models\Member;
use App\Services\ApprovalService;
use App\Services\RequestService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function membershipRegistration(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,organization_id'],
        ]);

        $user_id = auth()->id();
        $organization_id = $validated['organization_id'];

        // create a request for approval
        $action = "$user_id|$organization_id";
        $requestService = new RequestService;
        $requestService->createRequest($action, 1);

        return response()->json(['message' => 'Membership registration request submitted successfully']);
    }

    public function approveMembershipRequest($requestId) {
        $adminId = auth()->id();
        $response = ApprovalService::approveRequest($requestId, $adminId);
        return (is_string($response)) ? false : true;
    }

    public function create(){
        if (approveMembershipRequest(request()->route('requestId'))) {
            Member::create([
                'user_id' => request()->route('userId'),
                'organization_id' => request()->route('organizationId'),
            ]);
        } else {
            return response()->json(['message' => 'Failed to approve membership request'], 400);
        }
    }

    public function viewMembershipRequests(RequestService $requestService)
    {
        $requests = ActionRequest::query()
            ->where('request_action_type', 1)
            ->get();
        // parse format "[user_id]|[organization_id]" to get the user name and organization name for each request
        foreach ($requests as $request) {
            $action = $request->action ?? '';
            $data = explode('|', $action);
            $userId = $data[0] ?? null;
            $request->user = $userId ? \App\Models\User::find($userId) : null;
            $organizationId = $data[1] ?? null;
            $request->organization = $organizationId ? \App\Models\Organization::find($organizationId) : null;
            $request->request_time = $request->request_made_at;
            // return pending if no record of request_id in any item in approvals table, otherwise return approved or rejected based on the status in approvals table
            $approval = \App\Models\Approval::query()->where('request', $request->request_id)->first();
            $request->status = 'unknown';
            if (! $approval) {
                $request->status = 'pending';
            } else {
                $request->status = $approval->approved_at ? 'approved' : 'rejected';
            }
        }

        return view('admin.membership_requests', ['requests' => $requests]);
    }



}
