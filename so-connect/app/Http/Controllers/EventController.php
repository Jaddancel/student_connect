<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\ActionService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function createEventRequest(Request $request)
    {
        $validatedData = $request->validate([
            'organization' => auth()->user()->member->organization,
            'event_name' => 'required|string',
            'event_start_time' => 'required|datetime',
            'event_end_time' => 'required|datetime|after:event_start_time',
            'event_desc_text' => 'nullable|string',
        ]);

        (new ActionService)->passAction($validatedData, 1);
    }

    public function dashboard()
    {
        // get events and event details of the user's organization for the dashboard
        $events = Event::whereHas('organization', function ($query) {
            $query->whereIn('organization_id', auth()->user()->member()->pluck('organization')->toArray());
        })->with('details')->get();

        return view('calendar', compact('events'));
    }

    public function allEvents()
    {
        $events = Event::with('detail')->get()->map(function ($event) {
            return [
                'title' => $event->detail->event_name,
                'start' => $event->detail->event_start_date,
                'end' => $event->detail->event_end_date,
                'extendedProps' => [
                    'location' => $event->detail->event_location,
                    'description' => $event->detail->event_description_text,
                ],
            ];
        });

        return response()->json($events);
    }
}
