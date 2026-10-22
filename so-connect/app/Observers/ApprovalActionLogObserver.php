<?php

namespace App\Observers;

use App\Models\Approval;
use App\Models\Request as ActionRequest;
use App\Services\ActionLogger;

/**
 * Writes `request_accepted` to the action log, attributed to the approver
 * (the signed-in user), whenever an approval stage is recorded as accepted.
 * Approvals written without a session (seeders, console) are skipped.
 */
class ApprovalActionLogObserver
{
    public function saved(Approval $approval): void
    {
        if (auth()->id() === null || $approval->is_rejected || $approval->approved_at === null) {
            return;
        }

        if (! $approval->wasRecentlyCreated && ! $approval->wasChanged(['approved_at', 'is_rejected'])) {
            return;
        }

        $requestId = (int) $approval->request;
        $typeName = ActionRequest::query()
            ->whereKey($requestId)
            ->with('requestType:request_type_id,name')
            ->first()
            ?->requestType?->name;

        ActionLogger::log(
            ActionLogger::CATEGORY_REQUESTS,
            'request_accepted',
            'Accepted request #'.$requestId.($typeName ? ' ('.$typeName.')' : '')
                .($approval->stage ? ' at '.$approval->stage.' stage' : ''),
            array_filter([
                'request_id' => $requestId,
                'approval_id' => (int) $approval->getKey(),
                'stage' => $approval->stage,
            ], fn ($value) => $value !== null && $value !== ''),
            $approval,
        );
    }
}
