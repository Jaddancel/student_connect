<?php

namespace App\Http\Controllers;

use App\Models\Event;

class EventController extends Controller
{
    public function dashboard()
    {
        $events = Event::with('detail')
            ->whereHas('detail', function ($query) {
                $query->where('event_start_date', '>=', now());
            })
            ->get()
            ->sortBy(fn ($event) => $event->detail->event_start_date);

        return view('dashboard', compact('events'));
    }

    public function calendar()
    {
        $events = Event::with('detail')->get()
            ->sortBy(fn ($event) => $event->detail->event_start_date);

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
                ]
            ];
        });

        return response()->json($events);
    }
}
