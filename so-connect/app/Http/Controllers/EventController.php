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

        return view('dashboard', ['events' => $events]);
    }
}
