<?php

namespace App\Observers;

use App\Models\Request as ActionRequest;
use App\Services\ActionLogger;

/**
 * Writes `request_sent` to the action log for every request a signed-in user
 * creates, whichever handler/controller created it. Requests created without
 * a session (seeders, console) aren't user actions and are skipped.
 */
class RequestActionLogObserver
{
    public function created(ActionRequest $actionRequest): void
    {
        if (auth()->id() === null) {
            return;
        }

        $typeName = $actionRequest->requestType()->value('name');

        ActionLogger::log(
            ActionLogger::CATEGORY_REQUESTS,
            'request_sent',
            'Sent request #'.$actionRequest->getKey().($typeName ? ' ('.$typeName.')' : ''),
            array_filter([
                'request_id' => (int) $actionRequest->getKey(),
                'request_type_id' => $actionRequest->request_type_id,
                'form_id' => $actionRequest->form_id,
                'organization_id' => $actionRequest->organization_id,
            ], fn ($value) => $value !== null),
            $actionRequest,
        );
    }
}
