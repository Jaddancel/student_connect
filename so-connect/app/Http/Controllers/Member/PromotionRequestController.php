<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Services\RequestTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PromotionRequestController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) $request->user()->getKey();

        $orgs = DB::table('members as m')
            ->join('organizations as o', 'o.organization_id', '=', 'm.organization')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->leftJoin('organization_officers as oo', function ($join) {
                $join->on('oo.member', '=', 'm.member_id')
                    ->on('oo.organization', '=', 'm.organization');
            })
            ->where('m.user', $userId)
            ->select([
                'o.organization_id',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
                DB::raw("COALESCE(oo.`role`, 'member') as current_role"),
            ])
            ->get()
            ->unique('organization_id')
            ->filter(fn ($row) => $row->current_role !== 'president')
            ->values();

        $pendingRequestActions = ActionRequest::query()
            ->where('action_type', 7)
            ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
            ->get(['request_id', 'action', 'requested_at'])
            ->filter(function ($actionRequest) use ($userId) {
                [$targetUserId] = $this->parseRoleChangeAction($actionRequest->action);
                return $targetUserId === $userId;
            });

        $resolvedRequests = ActionRequest::query()
            ->where('action_type', 7)
            ->whereIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
            ->latest('requested_at')
            ->limit(10)
            ->get(['request_id', 'action', 'requested_at'])
            ->filter(function ($actionRequest) use ($userId) {
                [$targetUserId] = $this->parseRoleChangeAction($actionRequest->action);
                return $targetUserId === $userId;
            });

        $allOwnRequests = ActionRequest::query()
            ->where('action_type', 7)
            ->orderByDesc('requested_at')
            ->limit(50)
            ->get(['request_id', 'action', 'requested_at'])
            ->filter(function ($actionRequest) use ($userId) {
                [$targetUserId] = $this->parseRoleChangeAction($actionRequest->action);
                return $targetUserId === $userId;
            });

        $approvalMap = Approval::query()
            ->whereIn('request', $allOwnRequests->pluck('request_id')->all())
            ->get(['request', 'is_rejected', 'approved_at'])
            ->keyBy('request');

        $orgNameMap = $orgs->pluck('organization_name', 'organization_id')
            ->merge(
                DB::table('organizations as o')
                    ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                    ->whereIn('o.organization_id', $allOwnRequests->map(fn ($r) => $this->parseRoleChangeAction($r->action)[1])->unique()->filter()->values()->all())
                    ->pluck('od.name', 'o.organization_id')
            );

        $ownRequests = $allOwnRequests->map(function ($actionRequest) use ($approvalMap, $orgNameMap) {
            [$targetUserId, $organizationId, $currentRole] = $this->parseRoleChangeAction($actionRequest->action);
            $approval = $approvalMap->get((int) $actionRequest->request_id);
            $status = $approval === null ? 'pending' : ($approval->is_rejected ? 'rejected' : 'approved');

            return [
                'request_id' => (int) $actionRequest->request_id,
                'organization_name' => $orgNameMap[$organizationId] ?? 'Unknown Organization',
                'current_role' => $currentRole,
                'requested_role' => $this->nextRole($currentRole),
                'status' => $status,
                'requested_at' => $actionRequest->requested_at,
            ];
        })->values();

        return view('pages.member.promotion-request', [
            'title' => 'Request Promotion',
            'orgs' => $orgs,
            'ownRequests' => $ownRequests,
        ]);
    }

    public function store(Request $request, RequestTypeService $requestTypeService)
    {
        $userId = (int) $request->user()->getKey();

        $validated = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'organization_id')],
        ]);

        $organizationId = (int) $validated['organization_id'];

        $memberRow = DB::table('members as m')
            ->leftJoin('organization_officers as oo', function ($join) {
                $join->on('oo.member', '=', 'm.member_id')
                    ->on('oo.organization', '=', 'm.organization');
            })
            ->where('m.organization', $organizationId)
            ->where('m.user', $userId)
            ->select([
                'm.member_id',
                DB::raw("COALESCE(oo.`role`, 'member') as member_role"),
            ])
            ->first();

        if (! $memberRow) {
            return back()->with('status', 'You are not a member of the selected organization.');
        }

        $currentRole = in_array($memberRow->member_role, ['member', 'officer'], true)
            ? $memberRow->member_role
            : null;

        if ($currentRole === null) {
            return back()->with('status', 'You are already at the highest role in this organization.');
        }

        $payload = $userId.'|'.$organizationId.'|'.$currentRole;

        $hasPending = ActionRequest::query()
            ->where('action_type', 7)
            ->where('action', $payload)
            ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
            ->exists();

        if ($hasPending) {
            return back()->with('status', 'You already have a pending promotion request for this organization.');
        }

        $requestType = $requestTypeService->resolveSystemType(
            RequestType::SYSTEM_KEY_ROLE_CHANGE,
            'Role Change Request',
            RequestType::CATEGORY_ROLE_SECURITY,
            $userId,
        );

        ActionRequest::query()->create([
            'action' => $payload,
            'user' => $userId,
            'action_type' => 7,
            'request_type_id' => (int) $requestType->getKey(),
            'organization_id' => $organizationId,
            'requested_by' => $userId,
            'payload' => [
                'organization_id' => $organizationId,
                'target_user_id' => $userId,
                'current_role' => $currentRole,
                'member_initiated' => true,
            ],
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Promotion request submitted. You will be notified when it is reviewed.');
    }

    private function parseRoleChangeAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 3) {
            return [0, 0, 'member'];
        }

        $userId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $organizationId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        $currentRole = in_array($parts[2], ['member', 'officer', 'president'], true) ? $parts[2] : 'member';

        return [$userId, $organizationId, $currentRole];
    }

    private function nextRole(string $currentRole): string
    {
        return match ($currentRole) {
            'member' => 'officer',
            'officer' => 'president',
            default => 'president',
        };
    }
}
