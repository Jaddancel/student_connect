<?php

namespace App\Http\Controllers;

use App\Services\ActionService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function createEventRequest(Request $request)
    {
        $validatedData = $request->validate([
            'organization_id' => 'required|integer',
            'event_name' => 'required|string',
            'event_start_time' => 'required|datetime',
            'event_end_time' => 'required|datetime|after:event_start_time',
            'event_desc_text' => 'nullable|string',
        ]);

        (new ActionService)->passAction($validatedData, 1);
    }
}
