<?php

namespace App\Http\Controllers;

use App\Services\ActionService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function createReportRequest(Request $request)
    {
        $validatedData = $request->validate([
            'generated_for' => auth()->user() ?? null,
            'report_link' => 'required|string',
        ]);

        ((new ActionService)->passAction($validatedData, 5));

        // Here you would typically save the report to the database
        // For example:
        // Report::create($validatedData);
    }
}
