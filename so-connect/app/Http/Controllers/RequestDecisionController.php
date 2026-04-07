<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApprovalResource;
use App\Models\Approval;
use App\Models\Member;
use App\Models\Request as ActionRequest;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RequestDecisionController extends Controller
{
    public function store(Request $request, int $requestId): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
        ]);

        $actionRequest = ActionRequest::query()->findOrFail($requestId);
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $requiredDashboard = (int) $actionRequest->action_type === 3 ? 'president' : 'admin';

        if (! Gate::forUser($user)->allows('access-dashboard', $requiredDashboard)) {
            return response()->json([
                'message' => 'You are not authorized to decide this request.',
            ], 403);
        }

        $organizationId = OrganizationAuthorizationService::extractOrganizationIdFromAction($actionRequest->action);

        if ($organizationId !== null) {
            $authorizedOrganizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());

            if (! in_array($organizationId, $authorizedOrganizationIds, true)) {
                return response()->json([
                    'message' => 'You are not authorized to decide this request.',
                ], 403);
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

        return response()->json([
            'message' => $validated['decision'] === 'approve'
                ? 'Request approved successfully.'
                : 'Request rejected successfully.',
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
}
