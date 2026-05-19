<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\EventPlan;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\Semester;
use App\Models\Workplan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
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

    public function storeEventPlanRequest(Request $request, \App\Services\RequestTypeService $requestTypeService): JsonResponse
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
            'resources_needed' => ['nullable', 'string', 'max:5000'],
            'persons_responsible' => ['nullable', 'array'],
            'persons_responsible.*' => ['integer'],
            'purpose_of_activity' => ['nullable', 'string', 'max:1000'],
            'time_of_activity' => ['nullable', 'string', 'max:100'],
            'place_venue' => ['nullable', 'string', 'max:255'],
            'university_facilities' => ['nullable', 'array'],
            'university_facilities.*' => ['string', 'max:255'],
            'president_name' => ['nullable', 'string', 'max:255'],
            'president_contact' => ['nullable', 'string', 'max:50'],
            'faculty_advisers' => ['nullable', 'array'],
            'faculty_advisers.*' => ['string', 'max:255'],
            'college_dean' => ['nullable', 'string', 'max:255'],
            'activity_types' => ['nullable', 'array'],
            'activity_types.*' => ['string', 'max:100'],
            'activity_types_other' => ['nullable', 'string', 'max:255'],
            'area_scope' => ['nullable', 'string', 'max:100'],
            'area_scope_other' => ['nullable', 'string', 'max:255'],
            'sponsor' => ['nullable', 'string', 'max:100'],
            'sponsor_other' => ['nullable', 'string', 'max:255'],
            'extension_services' => ['nullable', 'in:yes,no'],
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
            'organization_id' => $organizationId,
            'requested_by' => $userId,
            'payload' => [],
            'user' => $userId,
            'requested_at' => now(),
        ]);

        $plan = EventPlan::query()->create([
            'organization_id' => $organizationId,
            'created_by' => $userId,
            'title' => $validated['title'],
            'target_date' => $validated['target_date'],
            'resources_needed' => $validated['resources_needed'] ?? null,
            'purpose_of_activity' => $validated['purpose_of_activity'] ?? null,
            'time_of_activity' => $validated['time_of_activity'] ?? null,
            'place_venue' => $validated['place_venue'] ?? null,
            'university_facilities' => array_values(array_filter($validated['university_facilities'] ?? [], fn ($value) => filled($value))),
            'president_name' => $validated['president_name'] ?? null,
            'president_contact' => $validated['president_contact'] ?? null,
            'faculty_advisers' => array_values(array_filter($validated['faculty_advisers'] ?? [], fn ($value) => filled($value))),
            'college_dean' => $validated['college_dean'] ?? null,
            'activity_types' => array_values(array_filter($validated['activity_types'] ?? [], fn ($value) => filled($value))),
            'activity_types_other' => $validated['activity_types_other'] ?? null,
            'area_scope' => $validated['area_scope'] ?? null,
            'area_scope_other' => $validated['area_scope_other'] ?? null,
            'sponsor' => $validated['sponsor'] ?? null,
            'sponsor_other' => $validated['sponsor_other'] ?? null,
            'extension_services' => isset($validated['extension_services']) ? $validated['extension_services'] === 'yes' : null,
            'persons_responsible' => $validated['persons_responsible'] ?? [],
            'status' => 'pending',
            'request_id' => (int) $actionRequest->getKey(),
        ]);

        $actionRequest->update([
            'payload' => [
                'event_plan_id' => (int) $plan->getKey(),
                'organization_id' => $organizationId,
                'user_id' => $userId,
            ],
        ]);

        return response()->json([
            'message' => 'Event plan submitted successfully.',
            'request_id' => (int) $actionRequest->getKey(),
        ], 201);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'organization_id')],
            'event_name' => ['required', 'string', 'max:255'],
            'event_start_time' => ['required', 'date'],
            'event_end_time' => ['required', 'date', 'after_or_equal:event_start_time'],
            'event_desc_text' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user();

        $eventDetail = EventDetail::create([
            'name' => $validated['event_name'],
            'desc_text' => $validated['event_desc_text'] ?? null,
            'start_time' => $validated['event_start_time'],
            'end_time' => $validated['event_end_time'],
            'location' => null,
        ]);

        Event::create([
            'creator' => (int) $user->getKey(),
            'event_detail' => (int) $eventDetail->getKey(),
            'organization' => (int) $validated['organization_id'],
        ]);

        return back()->with('success', 'Event created successfully.');
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

    private function resolveOrganizationColor(int $organizationId): string
    {
        $paletteSize = count(self::EVENT_COLORS);

        if ($organizationId <= 0 || $paletteSize === 0) {
            return 'Primary';
        }

        return self::EVENT_COLORS[($organizationId - 1) % $paletteSize];
    }
}
