<?php

namespace App\Services;

use App\Models\Approval;

class ApprovalService
{
    // This service will handle the logic for processing approvals.
    // For demonstration, we will just return a simple message.
    // In a real application, you would look up the approval by code and perform the necessary logic.
    public function processApproval($request_id)
    {
        Approval::create([
            'admin' => auth()->user()->getKey(),
            'approval_timestamp' => now(),
            'request_id' => $request_id,
        ]);
    }
}
