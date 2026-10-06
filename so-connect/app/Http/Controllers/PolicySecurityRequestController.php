<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApprovalResource;
use App\Http\Resources\PolicySecurityRequestResource;
use App\Models\Approval;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\User;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicySecurityRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isAdmin = (int) $user->user_type === 2;

        if (! $isAdmin) {
            $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey());

            if (empty($presidentOrganizationIds)) {
                return response()->json([
                    'message' => 'You are not authorized to access policy and security requests.',
                ], 403);
            }
        } else {
            $presidentOrganizationIds = null;
        }

        $hours = max((int) $request->query('hours', 0), 0);
        $limit = min(max((int) $request->query('limit', 0), 0), 200);

        $query = ActionRequest::query()
            ->where('action_type', 7)
            ->orderByDesc('requested_at');

        if ($hours > 0) {
            $query->where('requested_at', '>=', now()->subHours($hours));
        }

        $requests = $query->get();

        $parsedRequests = $requests
            ->map(function ($actionRequest) {
                [$payloadUserId, $organizationId, $currentRole] = $this->parseRoleChangeAction($actionRequest->action);

                return [
                    'request' => $actionRequest,
                    'payload_user_id' => $payloadUserId,
                    'organization_id' => $organizationId,
                    'current_role' => $currentRole,
                    'requested_role' => $this->nextRoleFromCurrent($currentRole),
                ];
            })
            ->filter(fn ($item) => $presidentOrganizationIds === null || in_array($item['organization_id'], $presidentOrganizationIds, true))
            ->values();

        if ($limit > 0) {
            $parsedRequests = $parsedRequests->take($limit)->values();
        }

        $organizationNameMap = Organization::query()
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'organizations.detail')
            ->whereIn('organizations.organization_id', $parsedRequests->pluck('organization_id')->unique()->all())
            ->pluck('od.name', 'organizations.organization_id')
            ->map(fn ($name) => $name ?: 'Unknown Organization')
            ->all();

        $userMap = User::query()
            ->whereIn('user_id', $parsedRequests->pluck('payload_user_id')->unique()->all())
            ->get()
            ->keyBy('user_id');

        $profileIds = $userMap
            ->pluck('profile')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $profileMap = Profile::query()
            ->whereIn('profile_id', $profileIds)
            ->get(['profile_id', 'first_name', 'middle_name', 'last_name', 'occupation'])
            ->keyBy('profile_id');

        $resourceItems = $parsedRequests->map(function ($item) use ($organizationNameMap, $userMap, $profileMap) {
            $actionRequest = $item['request'];
            $payloadUser = $userMap->get($item['payload_user_id']);
            $profile = $payloadUser ? $profileMap->get($payloadUser->profile) : null;
            $fullName = trim((string) (($profile->first_name ?? '').' '.($profile->last_name ?? '')));

            return (object) [
                'request_id' => (int) $actionRequest->request_id,
                'action' => (string) $actionRequest->action,
                'action_type' => (int) $actionRequest->action_type,
                'requested_at' => $actionRequest->requested_at,
                'payload_user_id' => (int) $item['payload_user_id'],
                'organization_id' => (int) $item['organization_id'],
                'organization_name' => $organizationNameMap[$item['organization_id']] ?? 'Unknown Organization',
                'current_role' => $item['current_role'],
                'requested_role' => $item['requested_role'],
                'name_or_title' => $fullName !== ''
                    ? $fullName.' ('.ucfirst($item['current_role']).' -> '.ucfirst($item['requested_role']).')'
                    : 'User #'.$item['payload_user_id'].' ('.ucfirst($item['current_role']).' -> '.ucfirst($item['requested_role']).')',
                'profile' => $profile
                    ? [
                        'first_name' => $profile->first_name,
                        'middle_name' => $profile->middle_name,
                        'last_name' => $profile->last_name,
                        'occupation' => $profile->occupation,
                    ]
                    : null,
            ];
        });

        return PolicySecurityRequestResource::collection($resourceItems);
    }

    public function approvals(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isAdmin = (int) $user->user_type === 2;

        if (! $isAdmin) {
            $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey());

            if (empty($presidentOrganizationIds)) {
                return response()->json([
                    'message' => 'You are not authorized to access policy and security approvals.',
                ], 403);
            }
        } else {
            $presidentOrganizationIds = null;
        }

        $requestIds = ActionRequest::query()
            ->where('action_type', 7)
            ->orderByDesc('requested_at')
            ->get()
            ->filter(function ($actionRequest) use ($presidentOrganizationIds) {
                if ($presidentOrganizationIds === null) {
                    return true;
                }
                [, $organizationId] = $this->parseRoleChangeAction($actionRequest->action);

                return in_array($organizationId, $presidentOrganizationIds, true);
            })
            ->pluck('request_id')
            ->map(fn ($requestId) => (int) $requestId)
            ->values()
            ->all();

        if (empty($requestIds)) {
            return ApprovalResource::collection(collect());
        }

        return ApprovalResource::collection(
            Approval::query()
                ->whereIn('request', $requestIds)
                ->orderByDesc('approved_at')
                ->get()
        );
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isAdmin = (int) $user->user_type === 2;

        if (! $isAdmin) {
            $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey());

            if (empty($presidentOrganizationIds)) {
                return response()->json([
                    'message' => 'You are not authorized to access policy and security metrics.',
                ], 403);
            }
        } else {
            $presidentOrganizationIds = null;
        }

        $orgQuery = Organization::query()
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'organizations.detail');

        if ($presidentOrganizationIds !== null) {
            $orgQuery->whereIn('organizations.organization_id', $presidentOrganizationIds);
        }

        $organizationNames = $orgQuery
            ->pluck('od.name')
            ->map(fn ($name) => $name ?: 'Unknown Organization')
            ->values()
            ->all();

        $policyRequestIds = ActionRequest::query()
            ->where('action_type', 7)
            ->get()
            ->filter(function ($actionRequest) use ($presidentOrganizationIds) {
                if ($presidentOrganizationIds === null) {
                    return true;
                }
                [, $organizationId] = $this->parseRoleChangeAction($actionRequest->action);

                return in_array($organizationId, $presidentOrganizationIds, true);
            })
            ->pluck('request_id')
            ->map(fn ($requestId) => (int) $requestId)
            ->values()
            ->all();

        $resolvedRequestIds = Approval::query()
            ->whereIn('request', $policyRequestIds)
            ->whereNotNull('request')
            ->pluck('request')
            ->map(fn ($requestId) => (int) $requestId)
            ->unique()
            ->values()
            ->all();

        $pendingCount = collect($policyRequestIds)
            ->reject(fn ($requestId) => in_array($requestId, $resolvedRequestIds, true))
            ->count();

        $officerQuery = \DB::table('organization_officers');

        if ($presidentOrganizationIds !== null) {
            $officerQuery->whereIn('organization', $presidentOrganizationIds);
        }

        $adminsCount = (int) (clone $officerQuery)->where('role', 'president')->count();
        $officersCount = (int) (clone $officerQuery)->where('role', 'officer')->count();
        $membershipsCount = (int) (clone $officerQuery)->where('role', 'member')->count();

        return response()->json([
            'data' => [
                'organization_names' => $organizationNames,
                'organization_label' => $isAdmin
                    ? 'All Organizations'
                    : (count($organizationNames) === 1
                        ? ($organizationNames[0] ?? 'Unknown Organization')
                        : 'Your Organizations'),
                'admins_count' => $adminsCount,
                'officers_count' => $officersCount,
                'memberships_count' => $membershipsCount,
                'pending_role_change_requests_count' => $pendingCount,
            ],
        ]);
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

    protected function nextRoleFromCurrent(string $currentRole): string
    {
        return match ($currentRole) {
            'member' => 'officer',
            'officer' => 'president',
            default => 'president',
        };
    }
}
