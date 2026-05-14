<?php

namespace App\Services;

use App\Helpers\FormTemplateHelper;
use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\Member;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use Illuminate\Support\Facades\DB;

class RequestApprovalService
{
    public function autoApproveIfPresident(ActionRequest $actionRequest, int $requesterUserId): ?Approval
    {
        if (! $this->shouldAutoApproveForPresident($actionRequest, $requesterUserId)) {
            return null;
        }

        return $this->approve($actionRequest, $requesterUserId);
    }

    public function approve(ActionRequest $actionRequest, int $approverUserId): Approval
    {
        $actionRequest->loadMissing('requestType');

        $actionType = (int) $actionRequest->action_type;
        $requestType = $actionRequest->requestType;
        $systemKey = (string) ($requestType?->system_key ?? '');

        if ($this->isDocumentGenerationRequest($actionType, $systemKey)) {
            app(DocumentGenerationService::class)->generateFromApprovedRequest($actionRequest, $approverUserId);
        }

        $approval = Approval::query()->updateOrCreate(
            ['request' => (int) $actionRequest->getKey()],
            [
                'admin' => $approverUserId,
                'approved_at' => now(),
                'is_rejected' => false,
            ]
        );

        if ($this->isMembershipRequest($actionType, $systemKey)) {
            [$targetOrganizationId, $targetUserId] = $this->resolveMembershipDetails($actionRequest);

            if ($targetOrganizationId > 0 && $targetUserId > 0) {
                $membership = Member::query()->firstOrCreate(
                    [
                        'organization' => $targetOrganizationId,
                        'user' => $targetUserId,
                    ],
                    [
                        'member_since' => now(),
                    ]
                );

                DB::table('organization_officers')
                    ->where('member', (int) $membership->getKey())
                    ->where('organization', $targetOrganizationId)
                    ->delete();
            }
        }

        if ($this->isRoleChangeRequest($actionType, $systemKey)) {
            [$targetUserId, $targetOrganizationId, $currentRole] = $this->resolveRoleChangeDetails($actionRequest);

            if ($targetOrganizationId > 0 && $targetUserId > 0) {
                $membership = Member::query()->firstOrCreate(
                    [
                        'organization' => $targetOrganizationId,
                        'user' => $targetUserId,
                    ],
                    [
                        'member_since' => now(),
                    ]
                );

                if ($currentRole === 'member') {
                    DB::table('organization_officers')->updateOrInsert(
                        [
                            'member' => (int) $membership->getKey(),
                            'organization' => $targetOrganizationId,
                        ],
                        [
                            'role' => 'officer',
                            'yearterm' => null,
                            'registered_at' => now(),
                            'reassigned_at' => now(),
                        ]
                    );
                }

                if ($currentRole === 'officer') {
                    DB::table('organization_officers')
                        ->where('member', (int) $membership->getKey())
                        ->where('organization', $targetOrganizationId)
                        ->update([
                            'role' => 'president',
                            'reassigned_at' => now(),
                        ]);

                    $hasPresidentRole = DB::table('organization_officers')
                        ->where('member', (int) $membership->getKey())
                        ->where('organization', $targetOrganizationId)
                        ->where('role', 'president')
                        ->exists();

                    if (! $hasPresidentRole) {
                        DB::table('organization_officers')->insert([
                            'member' => (int) $membership->getKey(),
                            'organization' => $targetOrganizationId,
                            'role' => 'president',
                            'yearterm' => null,
                            'registered_at' => now(),
                            'reassigned_at' => now(),
                        ]);
                    }
                }
            }
        }

        if ($this->isEventRequest($actionType, $systemKey)) {
            [$targetOrganizationId, $requesterUserId, $eventName, $eventStartTime, $eventEndTime, $eventDescription, $eventLocation] = $this->resolveEventDetails($actionRequest);

            if ($targetOrganizationId > 0 && $eventName !== '' && $eventStartTime !== '' && $eventEndTime !== '' && $eventLocation !== '') {
                $eventDetail = EventDetail::query()->create([
                    'name' => $eventName,
                    'location' => $eventLocation,
                    'desc_text' => $eventDescription,
                    'start_time' => $eventStartTime,
                    'end_time' => $eventEndTime,
                ]);

                Event::query()->create([
                    'organization' => $targetOrganizationId,
                    'creator' => $requesterUserId > 0 ? $requesterUserId : (int) $actionRequest->user,
                    'event_detail' => (int) $eventDetail->getKey(),
                ]);
            }
        }

        return $approval;
    }

    private function shouldAutoApproveForPresident(ActionRequest $actionRequest, int $requesterUserId): bool
    {
        $requestActionType = (int) $actionRequest->action_type;

        if ($requestActionType === 9) {
            return false;
        }

        $requestType = $actionRequest->relationLoaded('requestType')
            ? $actionRequest->requestType
            : $actionRequest->requestType()->first();

        if ((string) ($requestType?->system_key ?? '') === RequestType::SYSTEM_KEY_PROFILE_MATCH) {
            return false;
        }

        $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($requesterUserId);

        if (empty($presidentOrganizationIds)) {
            return false;
        }

        $requestOrganizationId = $this->requestOrganizationId($actionRequest, $requestActionType, (string) ($requestType?->system_key ?? ''));

        if ($requestOrganizationId === null) {
            return true;
        }

        return in_array($requestOrganizationId, $presidentOrganizationIds, true);
    }

    private function requestOrganizationId(ActionRequest $actionRequest, int $actionType, string $systemKey): ?int
    {
        $organizationId = (int) ($actionRequest->organization_id ?? 0);

        if ($organizationId > 0) {
            return $organizationId;
        }

        $payload = (array) ($actionRequest->payload ?? []);
        $payloadOrganizationId = (int) ($payload['organization_id'] ?? 0);

        if ($payloadOrganizationId > 0) {
            return $payloadOrganizationId;
        }

        if ($this->isMembershipRequest($actionType, $systemKey)) {
            return $this->parseMembershipAction($actionRequest->action)[0] ?: null;
        }

        if ($this->isRoleChangeRequest($actionType, $systemKey)) {
            return $this->parseRoleChangeAction($actionRequest->action)[1] ?: null;
        }

        if ($this->isEventRequest($actionType, $systemKey)) {
            return $this->parseEventAction($actionRequest->action)[0] ?: null;
        }

        if ($this->isDocumentGenerationRequest($actionType, $systemKey)
            || $systemKey === RequestType::SYSTEM_KEY_FORM_ACCESS
            || $actionType === FormTemplateHelper::ACTION_TYPE_DOCUMENT_ACCESS
            || $actionType === FormTemplateHelper::ACTION_TYPE_FORM_UPLOAD) {
            $organizationId = OrganizationAuthorizationService::extractOrganizationIdFromAction($actionRequest->action);

            return $organizationId > 0 ? $organizationId : null;
        }

        return null;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveMembershipDetails(ActionRequest $actionRequest): array
    {
        $payload = (array) ($actionRequest->payload ?? []);

        $organizationId = (int) ($payload['organization_id'] ?? 0);
        $targetUserId = (int) ($payload['user_id'] ?? 0);

        if ($organizationId > 0 && $targetUserId > 0) {
            return [$organizationId, $targetUserId];
        }

        return $this->parseMembershipAction($actionRequest->action);
    }

    /**
     * @return array{0:int,1:int,2:string}
     */
    private function resolveRoleChangeDetails(ActionRequest $actionRequest): array
    {
        $payload = (array) ($actionRequest->payload ?? []);

        $targetUserId = (int) ($payload['target_user_id'] ?? 0);
        $organizationId = (int) ($payload['organization_id'] ?? 0);
        $currentRole = in_array((string) ($payload['current_role'] ?? ''), ['member', 'officer', 'president'], true)
            ? (string) $payload['current_role']
            : 'member';

        if ($targetUserId > 0 && $organizationId > 0) {
            return [$targetUserId, $organizationId, $currentRole];
        }

        return $this->parseRoleChangeAction($actionRequest->action);
    }

    /**
     * @return array{0:int,1:int,2:string,3:string,4:string,5:string,6:string}
     */
    private function resolveEventDetails(ActionRequest $actionRequest): array
    {
        $payload = (array) ($actionRequest->payload ?? []);

        $organizationId = (int) ($payload['organization_id'] ?? 0);
        $requesterUserId = (int) ($payload['user_id'] ?? 0);
        $eventName = trim((string) ($payload['name'] ?? ''));
        $eventStartTime = trim((string) ($payload['start_time'] ?? ''));
        $eventEndTime = trim((string) ($payload['end_time'] ?? ''));
        $eventDescription = trim((string) ($payload['desc_text'] ?? ''));
        $eventLocation = trim((string) ($payload['location'] ?? ''));

        if ($organizationId > 0 && $requesterUserId > 0 && $eventName !== '' && $eventStartTime !== '' && $eventEndTime !== '' && $eventLocation !== '') {
            return [$organizationId, $requesterUserId, $eventName, $eventStartTime, $eventEndTime, $eventDescription, $eventLocation];
        }

        return $this->parseEventAction($actionRequest->action);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseMembershipAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 2) {
            return [0, 0];
        }

        $organizationId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $userId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;

        return [$organizationId, $userId];
    }

    /**
     * @return array{0:int,1:int,2:string}
     */
    private function parseRoleChangeAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 3) {
            return [0, 0, 'member'];
        }

        $userId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $organizationId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        $currentRole = in_array($parts[2], ['member', 'officer', 'president'], true)
            ? $parts[2]
            : 'member';

        return [$userId, $organizationId, $currentRole];
    }

    /**
     * @return array{0:int,1:int,2:string,3:string,4:string,5:string,6:string}
     */
    private function parseEventAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 7) {
            return [0, 0, '', '', '', '', ''];
        }

        $organizationId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $requesterUserId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        $eventName = $parts[2];
        $startTime = $parts[3];
        $endTime = $parts[4];
        $description = $parts[5];
        $location = implode('|', array_slice($parts, 6));

        return [$organizationId, $requesterUserId, $eventName, $startTime, $endTime, $description, $location];
    }

    private function isMembershipRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === 1 || $systemKey === RequestType::SYSTEM_KEY_MEMBERSHIP;
    }

    private function isRoleChangeRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === 7 || $systemKey === RequestType::SYSTEM_KEY_ROLE_CHANGE;
    }

    private function isEventRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === 2 || $systemKey === RequestType::SYSTEM_KEY_EVENT;
    }

    private function isDocumentGenerationRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION
            || $systemKey === RequestType::SYSTEM_KEY_FORM_GENERATION;
    }
}
