<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\eventDetails;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function approveMembershipRequest(RequestModel $membershipRequest, int $adminId): Approval
    {
        return DB::transaction(function () use ($membershipRequest, $adminId) {
            $approval = Approval::create([
                'admin' => $adminId,
                'approval_timestamp' => now(),
                'request' => $membershipRequest->request_id,
                'decision' => 'approved',
            ]);

            [$organizationId, $userId] = $this->parseMembershipRequestAction($membershipRequest->action);

            DB::table('organization_officers')->updateOrInsert(
                ['organization' => $organizationId, 'user' => $userId],
                ['role' => 'member', 'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now()]
            );

            return $approval;
        });
    }

    public function denyMembershipRequest(RequestModel $membershipRequest, int $adminId): Approval
    {
        return Approval::create([
            'admin' => $adminId,
            'approval_timestamp' => now(),
            'request' => $membershipRequest->request_id,
            'decision' => 'rejected',
        ]);
    }

    public function approveEventRequest(RequestModel $eventRequest, int $adminId): Approval
    {
        return DB::transaction(function () use ($eventRequest, $adminId) {
            $approval = Approval::create([
                'admin' => $adminId,
                'approval_timestamp' => now(),
                'request' => $eventRequest->request_id,
                'decision' => 'approved',
            ]);

            [$organizationId, $eventName, $eventStartTime, $eventEndTime, $eventDescText] = $this->parseEventRequestAction($eventRequest->action);

            $eventDetail = eventDetails::create([
                'event_name' => $eventName,
                'event_desc_text' => $eventDescText,
                'event_start_time' => $eventStartTime,
                'event_end_time' => $eventEndTime,
                'event_location' => null,
            ]);

            Event::create([
                'creator' => $adminId,
                'event_detail' => $eventDetail->event_detail_id,
                'organization' => $organizationId,
            ]);

            return $approval;
        });
    }

    public function denyEventRequest(RequestModel $eventRequest, int $adminId): Approval
    {
        return Approval::create([
            'admin' => $adminId,
            'approval_timestamp' => now(),
            'request' => $eventRequest->request_id,
            'decision' => 'rejected',
        ]);
    }

    protected function parseMembershipRequestAction(?string $action): array
    {
        $parts = explode('|', (string) $action);

        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Invalid membership request action payload.');
        }

        return [(int) $parts[0], (int) $parts[1]];
    }

    protected function parseEventRequestAction(?string $action): array
    {
        $parts = explode('|', (string) $action);

        if (count($parts) < 5) {
            throw new \InvalidArgumentException('Invalid event request action payload.');
        }

        $offset = count($parts) >= 6 ? 2 : 1;

        return [
            (int) $parts[0],
            $parts[$offset],
            $parts[$offset + 1],
            $parts[$offset + 2],
            $parts[$offset + 3],
        ];
    }
}
