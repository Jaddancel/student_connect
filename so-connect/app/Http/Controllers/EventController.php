<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
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

        $organizationIds = DB::table('members')
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

    public function storeEventRequest(Request $request, \App\Services\RequestTypeService $requestTypeService): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isOfficerOrPresident = Gate::forUser($user)->allows('access-dashboard', 'officer')
            || Gate::forUser($user)->allows('access-dashboard', 'president');

        if ((int) $user->user_type !== 2 && ! $isOfficerOrPresident) {
            return response()->json([
                'message' => 'Only organization officers and presidents can submit event requests.',
            ], 403);
        }

        $validated = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'organization_id')],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'desc_text' => ['required', 'string', 'max:5000'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after_or_equal:start_time'],
        ]);

        $userId = (int) $user->getKey();
        $organizationId = (int) $validated['organization_id'];

        $isOrganizationMember = DB::table('members')
            ->where('user', $userId)
            ->where('organization', $organizationId)
            ->exists();

        if (! $isOrganizationMember) {
            return response()->json([
                'message' => 'You can only request events for organizations you belong to.',
            ], 403);
        }

        $hasPresident = DB::table('members as m')
            ->join('organization_officers as oo', function ($join) {
                $join->on('oo.member', '=', 'm.member_id')
                    ->on('oo.organization', '=', 'm.organization');
            })
            ->where('m.organization', $organizationId)
            ->where('oo.role', 'president')
            ->exists();

        if (! $hasPresident) {
            return response()->json([
                'message' => 'No president is assigned to this organization yet.',
            ], 422);
        }

        $actionValue = implode('|', [
            $organizationId,
            $userId,
            $validated['name'],
            $validated['start_time'],
            $validated['end_time'],
            $validated['desc_text'],
            $validated['location'],
        ]);

        $hasPendingRequest = ActionRequest::query()
            ->where('action_type', 2)
            ->where('action', $actionValue)
            ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
            ->exists();

        if ($hasPendingRequest) {
            return response()->json([
                'message' => 'You already have a pending event request with the same details.',
            ], 422);
        }

        $requestType = $requestTypeService->resolveSystemType(
            RequestType::SYSTEM_KEY_EVENT,
            'Event Request',
            RequestType::CATEGORY_EVENT,
            $userId,
        );

        $actionRequest = ActionRequest::query()->create([
            'action' => implode('|', [
                $organizationId,
                $userId,
                $validated['name'],
                $validated['start_time'],
                $validated['end_time'],
                $validated['desc_text'],
                $validated['location'],
            ]),
            'action_type' => 2,
            'request_type_id' => (int) $requestType->getKey(),
            'organization_id' => $organizationId,
            'requested_by' => $userId,
            'payload' => [
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'name' => $validated['name'],
                'location' => $validated['location'],
                'desc_text' => $validated['desc_text'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
            ],
            'user' => $userId,
            'requested_at' => now(),
        ]);

        return response()->json([
            'message' => 'Event request submitted successfully.',
            'request_id' => (int) $actionRequest->getKey(),
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
