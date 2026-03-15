<?php

namespace App\Http\Controllers;

use App\Models\ActionRequest;
use App\Services\RequestService;

class ApprovalController extends Controller
{
    // public function view(RequestService $requestService, $actionTypeCode)
    // {
    // $requests = $requestService->getAllRequestByActionType($actionTypeCode);

    // return view('approvals.index', ['requests' => $requests]);
    // }

    public function approve($requestId, $adminId)
    {
        $approvalService = new \App\Services\ApprovalService;
        $response = $approvalService->approveRequest($requestId, $adminId);

        return (is_string($response))
            ? false
            : true;
    }

}
