<?php

namespace App\Http\Controllers;

use App\Models\ActionRequest;
use App\Services\RequestService;

class ApprovalController extends Controller
{
    public function get(RequestService $requestService, $actionTypeCode)
    {
        $requests = $requestService->getAllRequestByActionType($actionTypeCode);

        return response()->json($requests);
    }

    // public function view(RequestService $requestService, $actionTypeCode)
    // {
    // $requests = $requestService->getAllRequestByActionType($actionTypeCode);

    // return view('approvals.index', ['requests' => $requests]);
    // }

    public function membershipRequests(RequestService $requestService)
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

    public function approve($requestId, $adminId)
    {
        $approvalService = new \App\Services\ApprovalService;
        $response = $approvalService->approveRequest($requestId, $adminId);

        return response()->json($response);
    }
}
