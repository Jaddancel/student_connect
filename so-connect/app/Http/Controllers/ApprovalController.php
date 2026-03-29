<?php

namespace App\Http\Controllers;

use App\Models\Request as RequestModel;
use App\Services\ApprovalService;

class ApprovalController extends Controller
{
    public function approveMembershipRequest(RequestModel $membershipRequest, ApprovalService $approvalService)
    {
        $validation = $this->validateMembershipRequestAction($membershipRequest);
        if ($validation !== null) {
            return $validation;
        }

        $approvalService->approveMembershipRequest($membershipRequest, (int) auth()->id());

        return redirect()
            ->route('officer.membership_requests')
            ->with('success', 'Membership request approved successfully.');
    }

    public function denyMembershipRequest(RequestModel $membershipRequest, ApprovalService $approvalService)
    {
        $validation = $this->validateMembershipRequestAction($membershipRequest);
        if ($validation !== null) {
            return $validation;
        }

        $approvalService->denyMembershipRequest($membershipRequest, (int) auth()->id());

        return redirect()
            ->route('officer.membership_requests')
            ->with('success', 'Membership request rejected successfully.');
    }

    public function approveEventRequest(RequestModel $eventRequest, ApprovalService $approvalService)
    {
        $validation = $this->validateEventRequestAction($eventRequest);
        if ($validation !== null) {
            return $validation;
        }

        $approvalService->approveEventRequest($eventRequest, (int) auth()->id());

        return redirect()
            ->route('admin.event_requests')
            ->with('success', 'Event request approved successfully.');
    }

    public function denyEventRequest(RequestModel $eventRequest, ApprovalService $approvalService)
    {
        $validation = $this->validateEventRequestAction($eventRequest);
        if ($validation !== null) {
            return $validation;
        }

        $approvalService->denyEventRequest($eventRequest, (int) auth()->id());

        return redirect()
            ->route('admin.event_requests')
            ->with('success', 'Event request rejected successfully.');
    }

    protected function validateMembershipRequestAction(RequestModel $membershipRequest)
    {
        if ((string) $membershipRequest->action_type !== '0') {
            return redirect()
                ->route('officer.membership_requests')
                ->with('error', 'Only membership requests can be processed here.');
        }

        if ($membershipRequest->approval()->exists()) {
            return redirect()
                ->route('officer.membership_requests')
                ->with('error', 'This membership request has already been processed.');
        }

        [$organizationId] = $this->parseMembershipAction($membershipRequest->action);

        $authorizedOrgIds = auth()->user()
            ->member()
            ->pluck('organization')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! in_array($organizationId, $authorizedOrgIds, true)) {
            return redirect()
                ->route('officer.membership_requests')
                ->with('error', 'You are not authorized to process this request.');
        }

        return null;
    }

    protected function parseMembershipAction(?string $action): array
    {
        $parts = explode('|', (string) $action);

        if (count($parts) < 2) {
            return [0, 0];
        }

        return [(int) $parts[0], (int) $parts[1]];
    }

    protected function validateEventRequestAction(RequestModel $eventRequest)
    {
        if ((string) $eventRequest->action_type !== '1') {
            return redirect()
                ->route('admin.event_requests')
                ->with('error', 'Only event requests can be processed here.');
        }

        if ($eventRequest->approval()->exists()) {
            return redirect()
                ->route('admin.event_requests')
                ->with('error', 'This event request has already been processed.');
        }

        [$organizationId] = $this->parseEventAction($eventRequest->action);

        $authorizedOrgIds = auth()->user()
            ->member()
            ->whereHas('organizationrelation.organizationDetail', function ($query) {
                $query->whereColumn('organization_details.president', 'members.member_id');
            })
            ->pluck('organization')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! in_array($organizationId, $authorizedOrgIds, true)) {
            return redirect()
                ->route('admin.event_requests')
                ->with('error', 'Only organization presidents can process this event request.');
        }

        return null;
    }

    protected function parseEventAction(?string $action): array
    {
        $parts = explode('|', (string) $action);

        if (count($parts) < 5) {
            return [0];
        }

        return [(int) $parts[0]];
    }
}
