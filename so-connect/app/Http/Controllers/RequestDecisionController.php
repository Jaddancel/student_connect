<?php

namespace App\Http\Controllers;

use App\Helpers\FormTemplateHelper;
use App\Http\Resources\ApprovalResource;
use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\Member;
use App\Models\Request as ActionRequest;
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

        $actionRequest = ActionRequest::query()->findOrFail($requestId);
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isPresidentOnlyAction = in_array((int) $actionRequest->action_type, [2, 3, 4, 7], true);
        $requiredDashboard = $isPresidentOnlyAction ? 'president' : 'admin';

        if (! Gate::forUser($user)->allows('access-dashboard', $requiredDashboard)) {
            return response()->json([
                'message' => 'You are not authorized to decide this request.',
            ], 403);
        }

        if ((int) $actionRequest->action_type === 7) {
            [, $organizationId] = $this->parseRoleChangeAction($actionRequest->action);
            $authorizedOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey());

            if ($organizationId <= 0 || ! in_array($organizationId, $authorizedOrganizationIds, true)) {
                return response()->json([
                    'message' => 'You are not authorized to decide this request.',
                ], 403);
            }
        } else {
            $organizationId = OrganizationAuthorizationService::extractOrganizationIdFromAction($actionRequest->action);

            $authorizedOrganizationIds = in_array((int) $actionRequest->action_type, [2, 3, 4], true)
                ? OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey())
                : OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

            if ($organizationId !== null) {
                if (! in_array($organizationId, $authorizedOrganizationIds, true)) {
                    return response()->json([
                        'message' => 'You are not authorized to decide this request.',
                    ], 403);
                }
            }
        }

        $generatedDocumentId = null;

        if ((int) $actionRequest->action_type === FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION
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

        if ((int) $actionRequest->action_type === 1 && $validated['decision'] === 'approve') {
            [$targetOrganizationId, $targetUserId] = $this->parseMembershipAction($actionRequest->action);

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

        if ((int) $actionRequest->action_type === 7 && $validated['decision'] === 'approve') {
            [$targetUserId, $targetOrganizationId, $currentRole] = $this->parseRoleChangeAction($actionRequest->action);

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

        if ((int) $actionRequest->action_type === 2 && $validated['decision'] === 'approve') {
            [$targetOrganizationId, $requesterUserId, $eventName, $eventStartTime, $eventEndTime, $eventDescription, $eventLocation] = $this->parseEventAction($actionRequest->action);

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
