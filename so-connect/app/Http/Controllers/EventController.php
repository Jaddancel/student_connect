<?php

namespace App\Http\Controllers;

use App\Models\Event;

class EventController extends Controller
{
    public function dashboard()
    {
        $events = Event::with('details')
            ->whereHas('details', function ($query) {
                $query->where('event_start_time', '>=', now());
            })
            ->get()
            ->sortBy(fn ($event) => $event->details->event_start_date);

        return view('dashboard', compact('events'));
    }

    public function calendar()
    {
        $events = Event::with('details')->get()
            ->sortBy(fn ($event) => $event->details->event_start_date);

        return view('calendar', compact('events'));
    }

    public function allEvents()
    {
        $events = Event::with('details')->get()->map(function ($event) {
            return [
                'title' => $event->details->event_name,
                'start' => $event->details->event_start_time,
                'end' => $event->details->event_end_time,
                'extendedProps' => [
                    'location' => $event->details->event_location,
                    'description' => $event->details->event_desc_text,
                ],
            ];
        });

        return response()->json($events);
    }
}
