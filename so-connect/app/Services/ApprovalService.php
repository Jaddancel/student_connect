<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Member;
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

            Member::updateOrCreate(
                [
                    'organization' => $organizationId,
                    'user' => $userId,
                ],
                [
                    'approval_id' => $approval->approval_id,
                    'member_since' => now(),
                ]
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

    protected function parseMembershipRequestAction(?string $action): array
    {
        $parts = explode('|', (string) $action);

        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Invalid membership request action payload.');
        }

        return [(int) $parts[0], (int) $parts[1]];
    }
}
