<?php

namespace App\Http\Controllers;

use App\Models\EventPlan;
use App\Models\Semester;
use App\Models\Workplan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    private const EVENT_COLORS = ['Primary', 'Success', 'Warning', 'Danger'];

    public function calendarEvents(Request $request)
    {
        $userId = (int) $request->user()->getKey();

        $organizationIds = DB::table('organization_officers')
            ->where('user', $userId)
            ->pluck('organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values();

        if ($organizationIds->isEmpty()) {
            return response()->json([]);
        }

        $events = DB::table('events as e')
            ->join('event_details as ed', 'ed.event_detail_id', '=', 'e.event_detail')
            ->leftJoin('organizations as o', 'o.organization_id', '=', 'e.organization')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('e.organization', $organizationIds->all())
            ->orderBy('ed.start_time')
            ->get([
                'e.event_id',
                'e.organization as organization_id',
                'ed.name as event_name',
                'ed.desc_text as event_description',
                'ed.location as event_location',
                'ed.start_time',
                'ed.end_time',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
            ])
            ->map(function ($event) {
                $organizationId = (int) ($event->organization_id ?? 0);

                return [
                    'id' => (string) $event->event_id,
                    'title' => $event->event_name,
                    'start' => $event->start_time,
                    'end' => $event->end_time,
                    'extendedProps' => [
                        'calendar' => $this->resolveOrganizationColor($organizationId),
                        'location' => $event->event_location,
                        'description' => $event->event_description,
                        'organization' => $event->organization_name,
                    ],
                ];
            })
            ->values();

        return response()->json($events);
    }

    public function storeEventPlanRequest(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isOfficerOrPresident = Gate::forUser($user)->allows('access-dashboard', 'officer')
            || Gate::forUser($user)->allows('access-dashboard', 'president');

        if ((int) $user->user_type !== 2 && ! $isOfficerOrPresident) {
            return response()->json([
                'message' => 'Only organization officers and presidents can submit event plans.',
            ], 403);
        }

        $validated = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'organization_id')],
            'title' => ['required', 'string', 'max:255'],
            'target_date' => ['required', 'date'],
            'resources_needed' => ['required', 'string', 'max:5000'],
            'persons_responsible' => ['required', 'array', 'min:1'],
            'persons_responsible.*' => ['integer'],
            'purpose_of_activity' => ['required', 'string', 'max:1000'],
            'university_facilities' => ['required', 'array', 'min:1'],
            'university_facilities.*' => ['required', 'string', 'max:255'],
            'president_name' => ['required', 'string', 'max:255'],
            'president_contact' => ['required', 'string', 'max:50'],
            'faculty_advisers' => ['required', 'array', 'min:1'],
            'faculty_advisers.*' => ['required', 'string', 'max:255'],
            'college_dean' => ['nullable', 'string', 'max:255'],
            'activity_type' => ['required', 'string', 'in:Seminar,Clean Up Drive,Conference,Workshop,Preparation,Meeting,others'],
            'activity_type_other' => ['required_if:activity_type,others', 'nullable', 'string', 'max:255'],
            'seminar_level' => ['required_if:activity_type,Seminar', 'nullable', 'in:College,University'],
            'area_scope' => ['required', 'string', 'max:100'],
            'area_scope_other' => ['required_if:area_scope,others', 'nullable', 'string', 'max:255'],
            'sponsor' => ['required', 'string', 'max:100'],
            'sponsor_other' => ['required_if:sponsor,others', 'nullable', 'string', 'max:255'],
            'cosponsor_count' => ['required_if:sponsor,co-sponsors', 'nullable', 'integer', Rule::when($request->input('sponsor') === 'co-sponsors', ['min:2'])],
            'related_to_organization' => ['nullable', 'boolean'],
            'extension_services' => ['required', 'in:yes,no'],
        ]);

        $userId = (int) $user->getKey();
        $organizationId = (int) $validated['organization_id'];

        $isOrganizationMember = DB::table('organization_officers')
            ->where('user', $userId)
            ->where('organization', $organizationId)
            ->exists();

        if (! $isOrganizationMember) {
            return response()->json([
                'message' => 'You can only submit event plans for organizations you belong to.',
            ], 403);
        }

        // Block if the workplan for the active preparation semester is already finalized
        $activeSemester = Semester::currentlyActive();
        if ($activeSemester) {
            $workplanFinalized = Workplan::query()
                ->where('organization_id', $organizationId)
                ->where('semester_id', $activeSemester->semester_id)
                ->whereIn('status', ['finalized', 'archived'])
                ->exists();

            if ($workplanFinalized) {
                return response()->json([
                    'message' => 'Your organization\'s workplan for this semester has been finalized. New event plans cannot be submitted until the next preparation period begins.',
                ], 403);
            }
        }

        $plan = EventPlan::query()->create([
            'organization_id' => $organizationId,
            'created_by' => $userId,
            'title' => $validated['title'],
            'target_date' => $validated['target_date'],
            'resources_needed' => $validated['resources_needed'] ?? null,
            'purpose_of_activity' => $validated['purpose_of_activity'] ?? null,
            'university_facilities' => array_values(array_filter($validated['university_facilities'] ?? [], fn ($value) => filled($value))),
            'president_name' => $validated['president_name'] ?? null,
            'president_contact' => $validated['president_contact'] ?? null,
            'faculty_advisers' => array_values(array_filter($validated['faculty_advisers'] ?? [], fn ($value) => filled($value))),
            'college_dean' => $validated['college_dean'] ?? null,
            'activity_types' => [$validated['activity_type']],
            'activity_types_other' => $validated['activity_type_other'] ?? null,
            'seminar_level' => $validated['seminar_level'] ?? null,
            'area_scope' => $validated['area_scope'] ?? null,
            'area_scope_other' => $validated['area_scope_other'] ?? null,
            'sponsor' => $validated['sponsor'] ?? null,
            'sponsor_other' => $validated['sponsor_other'] ?? null,
            'cosponsor_count' => isset($validated['cosponsor_count']) ? (int) $validated['cosponsor_count'] : null,
            'related_to_organization' => !empty($validated['related_to_organization']),
            'extension_services' => isset($validated['extension_services']) ? $validated['extension_services'] === 'yes' : null,
            'persons_responsible' => $validated['persons_responsible'] ?? [],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Event plan submitted successfully.',
            'event_plan_id' => (int) $plan->getKey(),
        ], 201);
    }

    public function organizationOfficers(int $organizationId): JsonResponse
    {
        $officers = DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('oo.organization', $organizationId)
            ->whereIn('oo.role', ['officer', 'president'])
            ->select([
                'u.user_id',
                DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"),
            ])
            ->distinct()
            ->get();

        return response()->json($officers->map(fn ($o) => [
            'user_id' => $o->user_id,
            'name' => trim($o->name) ?: 'Officer #'.$o->user_id,
        ])->values());
    }

    public function storeDirectEventRequest(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isOfficerOrPresident = Gate::forUser($user)->allows('access-dashboard', 'officer')
            || Gate::forUser($user)->allows('access-dashboard', 'president');

        if ((int) $user->user_type !== 2 && ! $isOfficerOrPresident) {
            return response()->json([
                'message' => 'Only organization officers and presidents can submit activity requests.',
            ], 403);
        }

        $validated = $request->validate([
            'organization_id'        => ['required', 'integer', Rule::exists('organizations', 'organization_id')],
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
            'extension_services'     => ['required', 'in:yes,no'],
            'event_location'         => ['required', 'string', 'max:255'],
            'event_start_time'       => ['required', 'date'],
            'event_end_time'         => ['required', 'date', 'after_or_equal:event_start_time'],
        ]);

        $userId = (int) $user->getKey();
        $organizationId = (int) $validated['organization_id'];

        $isOrganizationMember = DB::table('organization_officers')
            ->where('user', $userId)
            ->where('organization', $organizationId)
            ->exists();

        if (! $isOrganizationMember) {
            return response()->json([
                'message' => 'You can only submit activity requests for organizations you belong to.',
            ], 403);
        }

        $targetDate = \Illuminate\Support\Carbon::parse($validated['target_date'])->startOfDay();
        $today = \Illuminate\Support\Carbon::today();
        if ($targetDate->lt($today)) {
            return response()->json([
                'message' => 'Activity requests cannot target a past date.',
            ], 422);
        }

        $runningSemester = Semester::query()
            ->where('starts_at', '<=', $today)
            ->orderByDesc('starts_at')
            ->first();

        if (! $runningSemester) {
            return response()->json([
                'message' => 'No active semester found for a direct activity request.',
            ], 422);
        }

        $facilities    = array_values(array_filter($validated['university_facilities'] ?? [], fn ($v) => filled($v)));
        $advisers      = array_values(array_filter($validated['faculty_advisers'], fn ($v) => filled($v)));
        $activityTypes = array_values(array_filter($validated['activity_types'], fn ($v) => filled($v)));

        $orgName = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', $organizationId)
            ->value(DB::raw("COALESCE(od.name, 'Unknown Organization')")) ?? 'Unknown Organization';

        $startTime = \Illuminate\Support\Carbon::parse($validated['event_start_time']);
        $endTime   = \Illuminate\Support\Carbon::parse($validated['event_end_time']);

        $roPayload = [
            'date'                             => $targetDate->format('Y-m-d'),
            'dayOfTheWeek'                     => $targetDate->format('l'),
            'organization'                     => $orgName,
            'organization_id'                  => $organizationId,
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

        $submission = \App\Models\FormSubmission::query()->create([
            'form_id'         => (int) $form->getKey(),
            'organization_id' => $organizationId,
            'submitted_by'    => $userId,
            'submitted_at'    => now(),
            'payload'         => $roPayload,
        ]);

        $sharedPlanFields = [
            'organization_id'       => $organizationId,
            'created_by'            => $userId,
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
        ];

        // The parent activity stays pending until the admin decides this
        // request; approval admits it to the workplan, rejection kills it.
        $parentPlan = EventPlan::query()->create(array_merge($sharedPlanFields, ['status' => 'pending']));

        $actionRequest = app(\App\Services\DocumentGenerationService::class)->createDocumentGenerationRequest(
            $organizationId,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        $childPlan = EventPlan::query()->create(array_merge($sharedPlanFields, [
            'event_location'  => $validated['event_location'],
            'event_start_time'=> $validated['event_start_time'],
            'event_end_time'  => $validated['event_end_time'],
            'status'          => 'pending',
            'parent_plan_id'  => (int) $parentPlan->getKey(),
            'request_id'      => (int) $actionRequest->getKey(),
        ]));

        $actionRequest->update([
            'payload' => array_merge((array) ($actionRequest->payload ?? []), [
                'event_plan_id'  => (int) $childPlan->getKey(),
                'parent_plan_id' => (int) $parentPlan->getKey(),
            ]),
        ]);

        return response()->json([
            'message' => 'Activity request submitted successfully. Awaiting admin approval.',
            'event_plan_id' => (int) $childPlan->getKey(),
        ], 201);
    }

    private function resolveOrganizationColor(int $organizationId): string
    {
        $paletteSize = count(self::EVENT_COLORS);

        if ($organizationId <= 0 || $paletteSize === 0) {
            return 'Primary';
        }

        return self::EVENT_COLORS[($organizationId - 1) % $paletteSize];
    }
}
