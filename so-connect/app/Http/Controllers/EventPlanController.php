<?php

namespace App\Http\Controllers;

use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Semester;
use App\Models\Workplan;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use App\Services\WorkplanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            // Activities awaiting the admin decision that admits them to the
            // workplan (no parent — the request's own plan carries the parent)
            'in_workplan' => $plans->where('status', 'pending')->whereNull('parent_plan_id')->values(),
            // Event item requests awaiting individual admin review (have a parent)
            'pending' => $plans->where('status', 'pending')->whereNotNull('parent_plan_id')->values(),
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
                    'can_finalize' => in_array((int) $orgId, $allOrgIds, true),
                ];
            }
        }

        $approvedPlanIds = $grouped['approved']->pluck('event_plan_id')->all();
        $pendingEventRequestParentIds = [];

        if (! empty($approvedPlanIds)) {
            $pendingEventRequestParentIds = EventPlan::query()
                ->whereIn('parent_plan_id', $approvedPlanIds)
                ->where('status', 'pending')
                ->pluck('parent_plan_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return view('pages.sidebar.event-plans', [
            'title' => 'Event Plans',
            'grouped' => $grouped,
            'personNames' => $personNames,
            'orgNames' => $orgNames,
            'activeSemester' => $activeSemester,
            'workplans' => $workplans,
            'pendingEventRequestParentIds' => $pendingEventRequestParentIds,
        ]);
    }

    public function finalize(Request $request, int $workplan_id, WorkplanService $workplanService): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $workplan = Workplan::query()->findOrFail($workplan_id);

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        if ((int) $user->user_type !== 2 && ! in_array((int) $workplan->organization_id, $officerOrgIds, true)) {
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

    public function storeEvent(Request $request, int $id, DocumentGenerationService $documentGenerationService): RedirectResponse
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

        $alreadyPending = EventPlan::query()
            ->where('parent_plan_id', $id)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return back()->withErrors(['plan' => 'An event creation request for this plan is already pending admin approval.']);
        }

        $officerOrgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $presidentOrgIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);
        $allOrgIds = array_unique(array_merge($officerOrgIds, $presidentOrgIds));

        if ((int) $user->user_type !== 2 && ! in_array((int) $plan->organization_id, $allOrgIds, true)) {
            abort(403);
        }

        $validated = $request->validate([
            'title'                  => ['required', 'string', 'max:255'],
            'target_date'            => ['required', 'date'],
            'purpose_of_activity'    => ['required', 'string', 'max:1000'],
            'university_facilities'  => ['nullable', 'array', 'max:10'],
            'university_facilities.*'=> ['nullable', 'string', 'max:255'],
            'president_name'         => ['required', 'string', 'max:255'],
            'president_contact'      => ['required', 'string', 'max:50'],
            'faculty_advisers'       => ['required', 'array', 'min:1'],
            'faculty_advisers.*'     => ['required', 'string', 'max:255'],
            'college_dean'           => ['nullable', 'string', 'max:255'],
            'activity_types'         => ['required', 'array', 'min:1'],
            'activity_types.*'       => ['string', 'in:Seminar,Clean Up Drive,Donation,Conference,Workshop,others'],
            'activity_type_other'    => ['nullable', 'string', 'max:255'],
            'area_scope'             => ['required', 'string', 'max:100'],
            'area_scope_other'       => ['required_if:area_scope,others', 'nullable', 'string', 'max:255'],
            'sponsor'                => ['required', 'string', 'max:100'],
            'sponsor_other'          => ['required_if:sponsor,others', 'nullable', 'string', 'max:255'],
            'extension_services'     => ['nullable', 'in:yes,no'],
            'event_location'         => ['required', 'string', 'max:255'],
            'event_start_time'       => ['required', 'date'],
            'event_end_time'         => ['required', 'date', 'after_or_equal:event_start_time'],
        ]);

        $orgId = (int) $plan->organization_id;
        $facilities = array_values(array_filter($validated['university_facilities'] ?? [], fn ($v) => filled($v)));
        $advisers   = array_values(array_filter($validated['faculty_advisers'], fn ($v) => filled($v)));
        $activityTypes = array_values(array_filter($validated['activity_types'], fn ($v) => filled($v)));

        $orgName = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', $orgId)
            ->value(DB::raw("COALESCE(od.name, 'Unknown Organization')")) ?? 'Unknown Organization';

        $targetDate = Carbon::parse($validated['target_date']);
        $startTime  = Carbon::parse($validated['event_start_time']);
        $endTime    = Carbon::parse($validated['event_end_time']);

        $roPayload = [
            'date'                             => $targetDate->format('Y-m-d'),
            'dayOfTheWeek'                     => $targetDate->format('l'),
            'organization'                     => $orgName,
            'organization_id'                  => $orgId,
            'projectActivity'                  => $validated['title'],
            'purposed'                         => $validated['purpose_of_activity'],
            'time'                             => $startTime->format('g:i A').' - '.$endTime->format('g:i A'),
            'placeAndVenue'                    => $validated['event_location'],
            'facilitiesOrEquipmentToBeUsedRow' => $facilities,
            'activityTypes'                    => $activityTypes,
            'activityTypeOther'                => $validated['activity_type_other'] ?? '',
            'areaScope'                        => $validated['area_scope'] ?? '',
            'areaScopeOther'                   => $validated['area_scope_other'] ?? '',
            'sponsor'                          => $validated['sponsor'] ?? '',
            'sponsorOther'                     => $validated['sponsor_other'] ?? '',
            'extensionServices'                => $validated['extension_services'] ?? '',
            'presidentName'                    => $validated['president_name'],
            'presidentContactNo'               => $validated['president_contact'],
            'adviserRow'                       => $advisers,
            'collegeDean'                      => $validated['college_dean'] ?? '',
        ];

        $form = \App\Forms\SystemFunction::formOrFail(\App\Forms\SystemFunction::NEW_EVENT);

        $submission = FormSubmission::query()->create([
            'form_id'         => (int) $form->getKey(),
            'organization_id' => $orgId,
            'submitted_by'    => $userId,
            'submitted_at'    => now(),
            'payload'         => $roPayload,
        ]);

        $actionRequest = $documentGenerationService->createDocumentGenerationRequest(
            $orgId,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        $eventPlan = EventPlan::query()->create([
            'organization_id'        => $orgId,
            'created_by'             => $userId,
            'title'                  => $validated['title'],
            'target_date'            => $validated['target_date'],
            'purpose_of_activity'    => $validated['purpose_of_activity'],
            'university_facilities'  => $facilities,
            'president_name'         => $validated['president_name'],
            'president_contact'      => $validated['president_contact'],
            'faculty_advisers'       => $advisers,
            'college_dean'           => $validated['college_dean'] ?? null,
            'activity_types'         => $activityTypes,
            'activity_types_other'   => $validated['activity_type_other'] ?? null,
            'area_scope'             => $validated['area_scope'] ?? null,
            'area_scope_other'       => $validated['area_scope_other'] ?? null,
            'sponsor'                => $validated['sponsor'] ?? null,
            'sponsor_other'          => $validated['sponsor_other'] ?? null,
            'extension_services'     => $validated['extension_services'] === 'yes',
            'event_location'         => $validated['event_location'],
            'event_start_time'       => $validated['event_start_time'],
            'event_end_time'         => $validated['event_end_time'],
            'status'                 => 'pending',
            'parent_plan_id'         => $id,
            'request_id'             => (int) $actionRequest->getKey(),
        ]);

        $actionRequest->update([
            'payload' => array_merge((array) ($actionRequest->payload ?? []), [
                'event_plan_id'  => (int) $eventPlan->getKey(),
                'parent_plan_id' => $id,
            ]),
        ]);

        return redirect()->route('event-plans')->with('success', 'Activity request submitted for admin approval.');
    }

    public function revise(Request $request, int $id, DocumentGenerationService $documentGenerationService): RedirectResponse
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

        if ($plan->isEventRequest()) {
            $validated = $request->validate([
                'title'                  => ['required', 'string', 'max:255'],
                'target_date'            => ['required', 'date'],
                'purpose_of_activity'    => ['required', 'string', 'max:1000'],
                'university_facilities'  => ['nullable', 'array', 'max:10'],
                'university_facilities.*'=> ['nullable', 'string', 'max:255'],
                'president_name'         => ['required', 'string', 'max:255'],
                'president_contact'      => ['required', 'string', 'max:50'],
                'faculty_advisers'       => ['required', 'array', 'min:1'],
                'faculty_advisers.*'     => ['required', 'string', 'max:255'],
                'college_dean'           => ['nullable', 'string', 'max:255'],
                'activity_types'         => ['required', 'array', 'min:1'],
                'activity_types.*'       => ['string', 'in:Seminar,Clean Up Drive,Donation,Conference,Workshop,others'],
                'activity_type_other'    => ['nullable', 'string', 'max:255'],
                'area_scope'             => ['required', 'string', 'max:100'],
                'area_scope_other'       => ['required_if:area_scope,others', 'nullable', 'string', 'max:255'],
                'sponsor'                => ['required', 'string', 'max:100'],
                'sponsor_other'          => ['required_if:sponsor,others', 'nullable', 'string', 'max:255'],
                'extension_services'     => ['nullable', 'in:yes,no'],
                'event_location'         => ['required', 'string', 'max:255'],
                'event_start_time'       => ['required', 'date'],
                'event_end_time'         => ['required', 'date', 'after_or_equal:event_start_time'],
            ]);

            $orgId = (int) $plan->organization_id;
            $facilities = array_values(array_filter($validated['university_facilities'] ?? [], fn ($v) => filled($v)));
            $advisers   = array_values(array_filter($validated['faculty_advisers'], fn ($v) => filled($v)));
            $activityTypes = array_values(array_filter($validated['activity_types'], fn ($v) => filled($v)));

            $orgName = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->where('o.organization_id', $orgId)
                ->value(DB::raw("COALESCE(od.name, 'Unknown Organization')")) ?? 'Unknown Organization';

            $targetDate = Carbon::parse($validated['target_date']);
            $startTime  = Carbon::parse($validated['event_start_time']);
            $endTime    = Carbon::parse($validated['event_end_time']);

            $roPayload = [
                'date'                             => $targetDate->format('Y-m-d'),
                'dayOfTheWeek'                     => $targetDate->format('l'),
                'organization'                     => $orgName,
                'organization_id'                  => $orgId,
                'projectActivity'                  => $validated['title'],
                'purposed'                         => $validated['purpose_of_activity'],
                'time'                             => $startTime->format('g:i A').' - '.$endTime->format('g:i A'),
                'placeAndVenue'                    => $validated['event_location'],
                'facilitiesOrEquipmentToBeUsedRow' => $facilities,
                'activityTypes'                    => $activityTypes,
                'activityTypeOther'                => $validated['activity_type_other'] ?? '',
                'areaScope'                        => $validated['area_scope'] ?? '',
                'areaScopeOther'                   => $validated['area_scope_other'] ?? '',
                'sponsor'                          => $validated['sponsor'] ?? '',
                'sponsorOther'                     => $validated['sponsor_other'] ?? '',
                'extensionServices'                => $validated['extension_services'] ?? '',
                'presidentName'                    => $validated['president_name'],
                'presidentContactNo'               => $validated['president_contact'],
                'adviserRow'                       => $advisers,
                'collegeDean'                      => $validated['college_dean'] ?? '',
            ];

            $form = \App\Forms\SystemFunction::formOrFail(\App\Forms\SystemFunction::NEW_EVENT);

            $submission = FormSubmission::query()->create([
                'form_id'         => (int) $form->getKey(),
                'organization_id' => $orgId,
                'submitted_by'    => $userId,
                'submitted_at'    => now(),
                'payload'         => $roPayload,
            ]);

            $actionRequest = $documentGenerationService->createDocumentGenerationRequest(
                $orgId,
                (int) $submission->getKey(),
                (int) $form->getKey(),
                $userId,
            );

            $actionRequest->update([
                'payload' => array_merge((array) ($actionRequest->payload ?? []), [
                    'event_plan_id'  => (int) $plan->getKey(),
                    'organization_id' => $orgId,
                ]),
            ]);

            $plan->update([
                'title'                 => $validated['title'],
                'target_date'           => $validated['target_date'],
                'purpose_of_activity'   => $validated['purpose_of_activity'],
                'university_facilities' => $facilities,
                'president_name'        => $validated['president_name'],
                'president_contact'     => $validated['president_contact'],
                'faculty_advisers'      => $advisers,
                'college_dean'          => $validated['college_dean'] ?? null,
                'activity_types'        => $activityTypes,
                'activity_types_other'  => $validated['activity_type_other'] ?? null,
                'area_scope'            => $validated['area_scope'] ?? null,
                'area_scope_other'      => $validated['area_scope_other'] ?? null,
                'sponsor'               => $validated['sponsor'] ?? null,
                'sponsor_other'         => $validated['sponsor_other'] ?? null,
                'extension_services'    => $validated['extension_services'] === 'yes',
                'event_location'        => $validated['event_location'],
                'event_start_time'      => $validated['event_start_time'],
                'event_end_time'        => $validated['event_end_time'],
                'request_id'            => (int) $actionRequest->getKey(),
                'status'                => 'pending',
            ]);
        } else {
            $validated = $request->validate([
                'title'                  => ['required', 'string', 'max:255'],
                'target_date'            => ['required', 'date'],
                'resources_needed'       => ['required', 'string', 'max:5000'],
                'persons_responsible'    => ['required', 'array', 'min:1'],
                'persons_responsible.*'  => ['integer'],
                'purpose_of_activity'    => ['required', 'string', 'max:1000'],
                'university_facilities'  => ['required', 'array', 'min:1'],
                'university_facilities.*'=> ['required', 'string', 'max:255'],
                'president_name'         => ['required', 'string', 'max:255'],
                'president_contact'      => ['required', 'string', 'max:50'],
                'faculty_advisers'       => ['required', 'array', 'min:1'],
                'faculty_advisers.*'     => ['required', 'string', 'max:255'],
                'college_dean'           => ['nullable', 'string', 'max:255'],
                'activity_type'          => ['required', 'string', 'in:Seminar,Clean Up Drive,Conference,Workshop,Preparation,Meeting,others'],
                'activity_type_other'    => ['required_if:activity_type,others', 'nullable', 'string', 'max:255'],
                'seminar_level'          => ['required_if:activity_type,Seminar', 'nullable', 'in:College,University'],
                'area_scope'             => ['required', 'string', 'max:100'],
                'area_scope_other'       => ['required_if:area_scope,others', 'nullable', 'string', 'max:255'],
                'sponsor'                => ['required', 'string', 'max:100'],
                'sponsor_other'          => ['required_if:sponsor,others', 'nullable', 'string', 'max:255'],
                'cosponsor_count'        => ['required_if:sponsor,co-sponsors', 'nullable', 'integer', Rule::when($request->input('sponsor') === 'co-sponsors', ['min:2'])],
                'related_to_organization'=> ['nullable', 'boolean'],
                'extension_services'     => ['nullable', 'in:yes,no'],
            ]);

            $plan->update([
                'title'                  => $validated['title'],
                'target_date'            => $validated['target_date'],
                'resources_needed'       => $validated['resources_needed'] ?? null,
                'purpose_of_activity'    => $validated['purpose_of_activity'] ?? null,
                'university_facilities'  => array_values(array_filter($validated['university_facilities'] ?? [], fn ($v) => filled($v))),
                'president_name'         => $validated['president_name'] ?? null,
                'president_contact'      => $validated['president_contact'] ?? null,
                'faculty_advisers'       => array_values(array_filter($validated['faculty_advisers'] ?? [], fn ($v) => filled($v))),
                'college_dean'           => $validated['college_dean'] ?? null,
                'activity_types'         => [$validated['activity_type']],
                'activity_types_other'   => $validated['activity_type_other'] ?? null,
                'seminar_level'          => $validated['seminar_level'] ?? null,
                'area_scope'             => $validated['area_scope'] ?? null,
                'area_scope_other'       => $validated['area_scope_other'] ?? null,
                'sponsor'                => $validated['sponsor'] ?? null,
                'sponsor_other'          => $validated['sponsor_other'] ?? null,
                'cosponsor_count'        => isset($validated['cosponsor_count']) ? (int) $validated['cosponsor_count'] : null,
                'related_to_organization'=> !empty($validated['related_to_organization']),
                'extension_services'     => isset($validated['extension_services']) ? $validated['extension_services'] === 'yes' : null,
                'persons_responsible'    => $validated['persons_responsible'] ?? [],
                'status'                 => 'pending',
            ]);
        }

        return redirect()->route('event-plans')->with('success', 'Event plan revised and resubmitted.');
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
