<?php

namespace App\Services;

use App\Models\ActionRequest;
use App\Models\Approval;

class ApprovalService
{
    public static function approveRequest($requestId, $adminId)
    {
        // find and get the request item
        $request = ActionRequest::find($requestId);
        $request_type = $request->actionType;
        self::parseRequest($request, $request_type);

        if (! $request) {
            return response()->json(['message' => 'Request not found'], 404);
        }

        Approval::create([
            'request_id' => $requestId,
            'approved_at' => now(),
            'admin_id' => $adminId,
        ]);

        return response()->json(['message' => 'Request approved successfully']);
    }

    // FORMAT: "[user_id]|[organization_id]"
    private static function parseRequest($request, $request_type)
    {
        $action = $request->action ?? '';
        $data = explode('|', $action);
        switch ($request_type) {
            case 1:
                DatabaseService::processMembershipRequest($action);
                break;

            default:
                // code...
                break;
        }
    }
}
