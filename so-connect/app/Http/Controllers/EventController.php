<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    private function resolveOrganizationColor(int $organizationId): string
    {
        $paletteSize = count(self::EVENT_COLORS);

        if ($organizationId <= 0 || $paletteSize === 0) {
            return 'Primary';
        }

        return self::EVENT_COLORS[($organizationId - 1) % $paletteSize];
    }
}
