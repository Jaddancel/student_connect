<?php

namespace App\Http\Controllers;

use App\Helpers\FormTemplateHelper;
use App\Http\Resources\ApprovalResource;
use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\Member;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RequestDecisionController extends Controller
{
    public function store(Request $request, int $requestId, DocumentGenerationService $documentGenerationService): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
        ]);

        $actionRequest = ActionRequest::query()->with('requestType')->findOrFail($requestId);
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $actionType = (int) $actionRequest->action_type;
        $requestType = $actionRequest->requestType;
        $systemKey = (string) ($requestType?->system_key ?? '');

        $requiredDashboard = $this->requiredDashboardForRequest($actionType, $systemKey);

        if (! Gate::forUser($user)->allows('access-dashboard', $requiredDashboard)) {
            return response()->json([
                'message' => 'You are not authorized to decide this request.',
            ], 403);
        }

        $authorizedOrganizationIds = $this->authorizedOrganizationIdsForRequest($actionType, $systemKey, (int) $user->getKey());

        if ($authorizedOrganizationIds !== null) {
            $organizationId = $this->requestOrganizationId($actionRequest, $actionType, $systemKey);

            if ($organizationId !== null && ! in_array($organizationId, $authorizedOrganizationIds, true)) {
                return response()->json([
                    'message' => 'You are not authorized to decide this request.',
                ], 403);
            }
        }

        $generatedDocumentId = null;

        if ($this->isDocumentGenerationRequest($actionType, $systemKey)
            && $validated['decision'] === 'approve') {
            try {
                $generatedDocument = $documentGenerationService->generateFromApprovedRequest(
                    $actionRequest,
                    (int) $user->getKey(),
                );

                $generatedDocumentId = (int) $generatedDocument->getKey();
            } catch (\Throwable $throwable) {
                return response()->json([
                    'message' => $throwable->getMessage(),
                ], 422);
            }
        }

        $approval = Approval::query()->updateOrCreate(
            ['request' => (int) $actionRequest->getKey()],
            [
                'admin' => (int) $user->getKey(),
                'approved_at' => now(),
                'is_rejected' => $validated['decision'] === 'reject',
            ]
        );

        if ($this->isMembershipRequest($actionType, $systemKey) && $validated['decision'] === 'approve') {
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

                // Membership approval should only grant member status in the receiving organization.
                DB::table('organization_officers')
                    ->where('member', (int) $membership->getKey())
                    ->where('organization', $targetOrganizationId)
                    ->delete();
            }
        }

        if ($this->isRoleChangeRequest($actionType, $systemKey) && $validated['decision'] === 'approve') {
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

        if ($this->isEventRequest($actionType, $systemKey) && $validated['decision'] === 'approve') {
            [$targetOrganizationId, $requesterUserId, $eventName, $eventStartTime, $eventEndTime, $eventDescription, $eventLocation] = $this->resolveEventDetails($actionRequest);

            if ($targetOrganizationId <= 0 || $eventName === '' || $eventStartTime === '' || $eventEndTime === '' || $eventLocation === '') {
                return response()->json([
                    'message' => 'Invalid event request payload.',
                ], 422);
            }

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

        return response()->json([
            'message' => $validated['decision'] === 'approve'
                ? 'Request approved successfully.'
                : 'Request rejected successfully.',
            'generated_document_id' => $generatedDocumentId,
            'data' => ApprovalResource::make($approval->fresh()),
        ]);
    }

    private function requiredDashboardForRequest(int $actionType, string $systemKey): string
    {
        if ($systemKey === RequestType::SYSTEM_KEY_PROFILE_MATCH || $actionType === 9) {
            return 'superadmin';
        }

        if ($this->isPresidentScopeRequest($actionType, $systemKey)) {
            return 'president';
        }

        return 'admin';
    }

    private function authorizedOrganizationIdsForRequest(int $actionType, string $systemKey, int $userId): ?array
    {
        if ($systemKey === RequestType::SYSTEM_KEY_PROFILE_MATCH || $actionType === 9) {
            return null;
        }

        if ($this->isPresidentScopeRequest($actionType, $systemKey)) {
            return OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        }

        return OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
    }

    private function isPresidentScopeRequest(int $actionType, string $systemKey): bool
    {
        return in_array($actionType, [2, 3, 4, 7, 8], true)
            || in_array($systemKey, [
                RequestType::SYSTEM_KEY_EVENT,
                RequestType::SYSTEM_KEY_ROLE_CHANGE,
                RequestType::SYSTEM_KEY_FORM_GENERATION,
                RequestType::SYSTEM_KEY_FORM_ACCESS,
                RequestType::SYSTEM_KEY_FORM_UPLOAD,
            ], true);
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

    protected function parseMembershipAction(?string $action): array
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
    protected function parseRoleChangeAction(?string $action): array
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
    protected function parseEventAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 7) {
            return [0, 0, '', '', '', '', ''];
        }

        $organizationId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $requesterUserId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        $eventName = $parts[2];
        $eventStartTime = $parts[3];
        $eventEndTime = $parts[4];
        $eventDescription = $parts[5];
        $eventLocation = implode('|', array_slice($parts, 6));

        return [$organizationId, $requesterUserId, $eventName, $eventStartTime, $eventEndTime, $eventDescription, $eventLocation];
    }
}
