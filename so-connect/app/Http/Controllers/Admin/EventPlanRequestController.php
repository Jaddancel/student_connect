<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\EventPlan;
use App\Models\Request as ActionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventPlanRequestController extends Controller
{
    public function index()
    {
        $requests = ActionRequest::query()
            ->where('action_type', 10)
            ->orderByDesc('requested_at')
            ->get(['request_id', 'action_type', 'organization_id', 'requested_by', 'user', 'payload', 'requested_at']);

        $requestIds = $requests->pluck('request_id')->all();

        $approvals = Approval::query()
            ->whereIn('request', $requestIds)
            ->get()
            ->keyBy('request');

        $eventPlanIds = $requests->map(fn ($r) => (int) (((array) ($r->payload ?? []))['event_plan_id'] ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $eventPlans = EventPlan::query()
            ->whereIn('event_plan_id', $eventPlanIds)
            ->get(['event_plan_id', 'title', 'target_date', 'organization_id', 'status'])
            ->keyBy('event_plan_id');

        $requesterIds = $requests->pluck('user')->filter()->unique()->values()->all();
        $requesterNames = [];
        if (! empty($requesterIds)) {
            $requesterNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $requesterIds)
                ->select('u.user_id', DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
                ->get()->pluck('name', 'user_id')->all();
        }

        $orgIds = $requests->pluck('organization_id')->filter()->unique()->merge(
            $eventPlans->pluck('organization_id')
        )->unique()->values()->all();

        $orgNames = [];
        if (! empty($orgIds)) {
            $orgNames = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $orgIds)
                ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                ->get()->pluck('name', 'organization_id')->all();
        }

        $rows = $requests->map(function ($req) use ($approvals, $eventPlans, $requesterNames, $orgNames) {
            $payload = (array) ($req->payload ?? []);
            $planId = (int) ($payload['event_plan_id'] ?? 0);
            $plan = $planId > 0 ? $eventPlans->get($planId) : null;
            $approval = $approvals->get($req->request_id);
            $orgId = (int) ($req->organization_id ?? ($plan?->organization_id ?? 0));

            return [
                'request' => $req,
                'plan' => $plan,
                'approval' => $approval,
                'requester_name' => $requesterNames[$req->user] ?? 'Unknown',
                'org_name' => $orgNames[$orgId] ?? 'Unknown Organization',
            ];
        });

        $pending = $rows->filter(fn ($r) => $r['approval'] === null)->values();
        $decided = $rows->filter(fn ($r) => $r['approval'] !== null)->take(30)->values();

        return view('pages.admin.event-plan-requests.index', [
            'title' => 'Event Plan Requests',
            'pending' => $pending,
            'decided' => $decided,
        ]);
    }

    public function decide(Request $request, int $requestId): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $actionRequest = ActionRequest::query()->findOrFail($requestId);

        if ((int) $actionRequest->action_type !== 10) {
            return back()->withErrors(['request' => 'This request is not an event plan request.']);
        }

        $approval = Approval::query()->updateOrCreate(
            ['request' => $requestId],
            [
                'admin' => $userId,
                'approved_at' => now(),
                'is_rejected' => $validated['decision'] === 'reject',
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]
        );

        $payload = (array) ($actionRequest->payload ?? []);
        $eventPlanId = (int) ($payload['event_plan_id'] ?? 0);

        if ($eventPlanId > 0) {
            $newStatus = $validated['decision'] === 'approve' ? 'approved' : 'rejected';
            EventPlan::query()->where('event_plan_id', $eventPlanId)->update(['status' => $newStatus]);
        }

        $message = $validated['decision'] === 'approve'
            ? 'Event plan approved successfully.'
            : 'Event plan rejected.';

        return redirect()->route('admin.event-plan-requests.index')->with('success', $message);
    }
}
