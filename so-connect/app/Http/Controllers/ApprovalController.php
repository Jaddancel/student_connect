<?php

namespace App\Http\Controllers;

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
        $requests = $requestService->getAllRequestByActionType(1);

        return view('admin.membership_requests', ['requests' => $requests]);
    }

    public function approve($requestId, $adminId)
    {
        $approvalService = new \App\Services\ApprovalService;
        $response = $approvalService->approveRequest($requestId, $adminId);

        return response()->json($response);
    }
}
