<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\EventPlan;
use App\Models\Organization;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\Semester;
use App\Models\Workplan;
use App\Services\OrganizationAuthorizationService;
use App\Services\RequestTypeService;
use App\Services\WorkplanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventPlanController extends Controller
{
    public function index(Request $request, WorkplanService $workplanService)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        $allOrgIds = array_unique(array_merge($officerOrgIds, $presidentOrgIds));

        if (empty($allOrgIds) && (int) $user->user_type !== 2) {
            return view('pages.sidebar.event-plans', [
                'title' => 'Event Plans',
                'grouped' => [],
                'organizations' => collect(),
                'activeSemester' => null,
                'workplans' => [],
            ]);
        }

        $query = EventPlan::query()->with(['event']);

        if ((int) $user->user_type !== 2) {
            $query->whereIn('organization_id', $allOrgIds);
        }

        $plans = $query->orderByDesc('created_at')->get();

        $grouped = [
            'pending' => $plans->where('status', 'pending')->values(),
            'approved' => $plans->where('status', 'approved')->values(),
            'rejected' => $plans->where('status', 'rejected')->values(),
            'junked' => $plans->where('status', 'junked')->values(),
        ];

        $personIds = $plans->flatMap(fn ($p) => $p->persons_responsible ?? [])->unique()->filter()->values()->all();
        $personNames = [];

        if (! empty($personIds)) {
            $personNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $personIds)
                ->select('u.user_id', DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
                ->get()
                ->pluck('name', 'user_id')
                ->all();
        }

        $orgIds = $plans->pluck('organization_id')->unique()->filter()->values()->all();
        $orgNames = [];

        if (! empty($orgIds)) {
            $orgNames = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $orgIds)
                ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                ->get()
                ->pluck('name', 'organization_id')
                ->all();
        }

        $workplanService->archiveExpired();
        $activeSemester = $workplanService->getActiveSemester();

        $workplanOrgIds = (int) $user->user_type === 2 ? array_keys($orgNames) : $allOrgIds;
        $workplans = [];

        if ($activeSemester) {
            foreach ($workplanOrgIds as $orgId) {
                $wp = $workplanService->resolveWorkplan((int) $orgId, (int) $activeSemester->semester_id);
                $workplans[(int) $orgId] = [
                    'workplan' => $wp,
                    'semester' => $activeSemester,
                    'plans' => $workplanService->getApprovedPlansForWorkplan($wp),
                    'can_finalize' => in_array((int) $orgId, $presidentOrgIds, true),
                ];
            }
        }

        return view('pages.sidebar.event-plans', [
            'title' => 'Event Plans',
            'grouped' => $grouped,
            'personNames' => $personNames,
            'orgNames' => $orgNames,
            'activeSemester' => $activeSemester,
            'workplans' => $workplans,
        ]);
    }

    public function finalize(Request $request, int $workplan_id, WorkplanService $workplanService): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $workplan = Workplan::query()->findOrFail($workplan_id);

        $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        if (! in_array((int) $workplan->organization_id, $presidentOrgIds, true)) {
            abort(403);
        }

        if ($workplan->status !== 'active') {
            return back()->withErrors(['workplan' => 'Only active workplans can be finalized.']);
        }

        $semester = $workplan->semester;
        if (! $semester->isCurrentlyActive()) {
            return back()->withErrors(['workplan' => 'The preparation period for this workplan has ended.']);
        }

        $workplan->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $userId,
        ]);

        return redirect()->route('event-plans')->with('success', 'Workplan finalized. PDF generation is now available.');
    }

    public function storeEvent(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $plan = EventPlan::query()->findOrFail($id);

        if ($plan->status !== 'approved') {
            return back()->withErrors(['plan' => 'Only approved plans can be converted to events.']);
        }

        if ($plan->event_id !== null) {
            return back()->withErrors(['plan' => 'This plan already has an associated event.']);
        }

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        $allOrgIds = array_unique(array_merge($officerOrgIds, $presidentOrgIds));

        if ((int) $user->user_type !== 2 && ! in_array((int) $plan->organization_id, $allOrgIds, true)) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'desc_text' => ['nullable', 'string', 'max:5000'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after_or_equal:start_time'],
        ]);

        $eventDetail = EventDetail::query()->create([
            'name' => $validated['name'],
            'location' => $validated['location'],
            'desc_text' => $validated['desc_text'] ?? '',
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]);

        $event = Event::query()->create([
            'organization' => (int) $plan->organization_id,
            'creator' => $userId,
            'event_detail' => (int) $eventDetail->getKey(),
        ]);

        $plan->update(['event_id' => (int) $event->getKey()]);

        return redirect()->route('event-plans')->with('success', 'Event created successfully and added to the calendar.');
    }

    public function revise(Request $request, int $id, RequestTypeService $requestTypeService): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $plan = EventPlan::query()->findOrFail($id);

        if ($plan->status !== 'rejected') {
            return back()->withErrors(['plan' => 'Only rejected plans can be revised.']);
        }

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        $allOrgIds = array_unique(array_merge($officerOrgIds, $presidentOrgIds));

        if ((int) $user->user_type !== 2 && ! in_array((int) $plan->organization_id, $allOrgIds, true)) {
            abort(403);
        }

        $activeSemester = Semester::currentlyActive();
        if ($activeSemester) {
            $workplanFinalized = Workplan::query()
                ->where('organization_id', $plan->organization_id)
                ->where('semester_id', $activeSemester->semester_id)
                ->whereIn('status', ['finalized', 'archived'])
                ->exists();

            if ($workplanFinalized) {
                return back()->withErrors(['plan' => 'Your organization\'s workplan for this semester has been finalized. Plans cannot be resubmitted until the next preparation period begins.']);
            }
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_date' => ['required', 'date'],
            'resources_needed' => ['nullable', 'string', 'max:5000'],
            'persons_responsible' => ['nullable', 'array'],
            'persons_responsible.*' => ['integer'],
        ]);

        $requestType = $requestTypeService->resolveSystemType(
            RequestType::SYSTEM_KEY_EVENT_PLAN,
            'Event Plan Request',
            RequestType::CATEGORY_EVENT,
            $userId,
        );

        $actionRequest = ActionRequest::query()->create([
            'action' => '',
            'action_type' => 10,
            'request_type_id' => (int) $requestType->getKey(),
            'organization_id' => (int) $plan->organization_id,
            'requested_by' => $userId,
            'payload' => [],
            'user' => $userId,
            'requested_at' => now(),
        ]);

        $plan->update([
            'title' => $validated['title'],
            'target_date' => $validated['target_date'],
            'resources_needed' => $validated['resources_needed'] ?? null,
            'persons_responsible' => $validated['persons_responsible'] ?? [],
            'status' => 'pending',
            'request_id' => (int) $actionRequest->getKey(),
        ]);

        $actionRequest->update([
            'payload' => [
                'event_plan_id' => (int) $plan->getKey(),
                'organization_id' => (int) $plan->organization_id,
                'user_id' => $userId,
            ],
        ]);

        return redirect()->route('event-plans')->with('success', 'Event plan revised and resubmitted for approval.');
    }

    public function junk(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $plan = EventPlan::query()->findOrFail($id);

        if (! in_array($plan->status, ['pending', 'approved'], true)) {
            return back()->withErrors(['plan' => 'Only pending or approved plans can be junked.']);
        }

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        $allOrgIds = array_unique(array_merge($officerOrgIds, $presidentOrgIds));

        if ((int) $user->user_type !== 2 && ! in_array((int) $plan->organization_id, $allOrgIds, true)) {
            abort(403);
        }

        $plan->update(['status' => 'junked']);

        return redirect()->route('event-plans')->with('success', 'Event plan junked.');
    }
}
