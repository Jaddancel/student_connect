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

        return view('student.calendar', compact('events'));
    }
}
