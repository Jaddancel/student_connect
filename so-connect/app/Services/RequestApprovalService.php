<?php

namespace App\Services;

use App\Forms\Handlers\NewOrganizationRegistrationHandler;
use App\Helpers\FormTemplateHelper;
use App\Mail\NewOrganizationInvitationMail;
use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\EventPlan;
use App\Models\Organization;
use App\Models\Organization\OrganizationDetail;
use App\Models\OrganizationInvitation;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
                'rejection_reason' => null,
            ]
        );

        // Link the document generated above back to its approval (matches the
        // per-form admin pages, which read GeneratedDocument.approval_id).
        if ($this->isDocumentGenerationRequest($actionType, $systemKey)) {
            \App\Models\GeneratedDocument::query()
                ->where('request_id', (int) $actionRequest->getKey())
                ->whereNull('approval_id')
                ->update(['approval_id' => (int) $approval->getKey()]);
        }

        if ($this->isMembershipRequest($actionType, $systemKey)) {
            [$targetOrganizationId, $targetUserId] = $this->resolveMembershipDetails($actionRequest);

            if ($targetOrganizationId > 0 && $targetUserId > 0) {
                DB::table('organization_officers')
                    ->where('user', $targetUserId)
                    ->where('organization', $targetOrganizationId)
                    ->whereIn('role', ['officer', 'president'])
                    ->delete();

                DB::table('organization_officers')->updateOrInsert(
                    ['user' => $targetUserId, 'organization' => $targetOrganizationId],
                    ['role' => 'member', 'member_since' => now(), 'registered_at' => now(), 'reassigned_at' => now()]
                );
            }
        }

        if ($this->isRoleChangeRequest($actionType, $systemKey)) {
            [$targetUserId, $targetOrganizationId, $currentRole] = $this->resolveRoleChangeDetails($actionRequest);

            if ($targetOrganizationId > 0 && $targetUserId > 0) {
                if ($currentRole === 'member') {
                    DB::table('organization_officers')->updateOrInsert(
                        ['user' => $targetUserId, 'organization' => $targetOrganizationId],
                        ['role' => 'officer', 'yearterm' => null, 'registered_at' => now(), 'reassigned_at' => now()]
                    );
                }

                if ($currentRole === 'officer') {
                    DB::table('organization_officers')
                        ->where('user', $targetUserId)
                        ->where('organization', $targetOrganizationId)
                        ->update(['role' => 'president', 'reassigned_at' => now()]);

                    $hasPresidentRole = DB::table('organization_officers')
                        ->where('user', $targetUserId)
                        ->where('organization', $targetOrganizationId)
                        ->where('role', 'president')
                        ->exists();

                    if (! $hasPresidentRole) {
                        DB::table('organization_officers')->insert([
                            'user' => $targetUserId,
                            'organization' => $targetOrganizationId,
                            'role' => 'president',
                            'yearterm' => null,
                            'member_since' => now(),
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

        if ($this->isEventPlanRequest($actionType, $systemKey)) {
            $payload = (array) ($actionRequest->payload ?? []);
            $eventPlanId = (int) ($payload['event_plan_id'] ?? 0);

            if ($eventPlanId > 0) {
                EventPlan::query()->where('event_plan_id', $eventPlanId)->update(['status' => 'approved']);
            }
        }

        if ($this->isNewOrganizationRequest($actionType, $systemKey)) {
            $this->createOrganizationFromRequest($actionRequest, $approval);
        }

        return $approval;
    }

    /**
     * The reject counterpart of {@see approve()}: records the rejection (with
     * an optional reason) and flips the event-plan status where applicable —
     * rejection has no other domain side effects (the requester starts over).
     */
    public function reject(ActionRequest $actionRequest, int $adminUserId, ?string $reason = null): Approval
    {
        $actionRequest->loadMissing('requestType');

        $actionType = (int) $actionRequest->action_type;
        $systemKey = (string) ($actionRequest->requestType?->system_key ?? '');

        $approval = Approval::query()->updateOrCreate(
            ['request' => (int) $actionRequest->getKey()],
            [
                'admin' => $adminUserId,
                'approved_at' => now(),
                'is_rejected' => true,
                'rejection_reason' => ($reason !== null && trim($reason) !== '') ? trim($reason) : null,
            ]
        );

        if ($this->isEventPlanRequest($actionType, $systemKey)) {
            $payload = (array) ($actionRequest->payload ?? []);
            $eventPlanId = (int) ($payload['event_plan_id'] ?? 0);

            if ($eventPlanId > 0) {
                EventPlan::query()->where('event_plan_id', $eventPlanId)->update(['status' => 'rejected']);
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

        if ($this->isEventPlanRequest($requestActionType, (string) ($requestType?->system_key ?? ''))) {
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

        if ($this->isEventPlanRequest($actionType, $systemKey)) {
            return $payloadOrganizationId > 0 ? $payloadOrganizationId : null;
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

    private function isEventPlanRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === 10 || $systemKey === RequestType::SYSTEM_KEY_EVENT_PLAN;
    }

    private function isDocumentGenerationRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION
            || $systemKey === RequestType::SYSTEM_KEY_FORM_GENERATION;
    }

    private function isNewOrganizationRequest(int $actionType, string $systemKey): bool
    {
        return $actionType === NewOrganizationRegistrationHandler::ACTION_TYPE
            || $systemKey === RequestType::SYSTEM_KEY_NEW_ORGANIZATION_REGISTRATION;
    }

    /**
    * Create the organization from an approved new-organization request and
    * either attach existing leaders or issue their invitations.
     */
    private function createOrganizationFromRequest(ActionRequest $actionRequest, Approval $approval): void
    {
        $payload = (array) ($actionRequest->payload ?? []);

        $handler = app(NewOrganizationRegistrationHandler::class);
        $form = $actionRequest->form;
        $presidentEmail = $form !== null
            ? $handler->resolvePresidentEmail($form, $payload)
            : (is_string($payload['president_email'] ?? null) ? strtolower(trim($payload['president_email'])) : null);
        $officerEmail = $form !== null
            ? $handler->resolveOfficerEmail($form, $payload)
            : (is_string($payload['officer_email'] ?? null) ? strtolower(trim($payload['officer_email'])) : null);

        if ($presidentEmail === null || $presidentEmail === '' || $officerEmail === null || $officerEmail === '') {
            return;
        }

        $name = trim((string) ($this->payloadOrganizationValue($payload, $form, 'organization_name')));
        $initials = trim((string) ($this->payloadOrganizationValue($payload, $form, 'organization_initials')));
        $description = trim((string) ($this->payloadOrganizationValue($payload, $form, 'organization_description')));
        $type = (int) ($this->payloadOrganizationValue($payload, $form, 'organization_type') ?? 1);

        if ($name === '' || $initials === '') {
            return;
        }

        $existingOrg = OrganizationDetail::query()->where('name', $name)->first();
        if ($existingOrg !== null) {
            return;
        }

        DB::transaction(function () use ($actionRequest, $approval, $presidentEmail, $officerEmail, $name, $initials, $description, $type) {
            $detail = OrganizationDetail::query()->create([
                'name' => $name,
                'initials' => $initials,
                'detail_text' => $description !== '' ? $description : $name,
            ]);

            $organization = Organization::query()->create([
                'detail' => (int) $detail->getKey(),
                'organization_type' => $type,
                'accreditation_status' => \App\Services\AccreditationService::STATUS_ACTIVE ?? 'active',
            ]);

            $organizationId = (int) $organization->getKey();

            foreach ([
                ['email' => $presidentEmail, 'role' => 'president', 'position' => null],
                ['email' => $officerEmail, 'role' => 'officer', 'position' => 'other'],
            ] as $leader) {
                $leaderUser = User::query()->where('user_email', $leader['email'])->first();

                if ($leaderUser !== null && in_array((int) $leaderUser->user_type, [User::TYPE_SUPERADMIN, User::TYPE_ADMIN, User::TYPE_OFFICER], true)) {
                    DB::table('organization_officers')->updateOrInsert(
                        ['user' => (int) $leaderUser->getKey(), 'organization' => $organizationId],
                        [
                            'approval' => (int) $approval->getKey(),
                            'role' => $leader['role'],
                            'position' => $leader['position'],
                            'yearterm' => null,
                            'member_since' => now(),
                            'registered_at' => now(),
                            'reassigned_at' => now(),
                        ]
                    );

                    continue;
                }

                $rawToken = Str::random(64);
                OrganizationInvitation::query()->create([
                    'email' => $leader['email'],
                    'token_hash' => hash('sha256', $rawToken),
                    'organization_id' => $organizationId,
                    'request_id' => (int) $actionRequest->getKey(),
                    'role' => $leader['role'],
                    'position' => $leader['position'],
                    'expires_at' => now()->addDays(7),
                ]);

                try {
                    Mail::to($leader['email'])->send(new NewOrganizationInvitationMail(
                        recipientEmail: $leader['email'],
                        organizationName: $name,
                        role: $leader['role'],
                        activationUrl: route('organization-invitation.redeem', ['token' => $rawToken]),
                    ));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('New organization invitation email failed: '.$e->getMessage());
                }
            }
        });
    }

    /**
     * Resolve an organization detail value from the payload, falling back to
     * a field whose universal_key matches the well-known key.
     *
     * @param  array<string,mixed>  $payload
     */
    private function payloadOrganizationValue(array $payload, ?\App\Models\Form $form, string $key): mixed
    {
        if ($form !== null) {
            foreach ($form->fields as $field) {
                if (($field->universal_key === $key || $field->field_key === $key) && array_key_exists($field->field_key, $payload)) {
                    return $payload[$field->field_key];
                }
            }
        }

        return $payload[$key] ?? null;
    }
}
