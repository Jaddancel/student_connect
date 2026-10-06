<?php

namespace App\Http\Controllers;

use App\Services\AfterEventReportService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;

/**
 * "After Event Form" page: an organization official's list of concluded events
 * that need (or already have) an After Event Report this semester.
 */
class AfterEventReportController extends Controller
{
    public function index(Request $request, AfterEventReportService $afterEvents)
    {
        $user = $request->user();
        abort_unless((int) $user->user_type === 3, 403, 'The After Event Form page is for organization officials.');

        $organizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());
        abort_if($organizationIds === [], 403, 'The After Event Form page is for organization officials.');

        $form = $afterEvents->form();
        $window = $afterEvents->semesterWindow();

        return view('pages.after-event-reports.index', [
            'title' => 'After Event Form',
            'events' => $afterEvents->eligibleEvents($organizationIds),
            'form' => $form,
            'enabled' => $afterEvents->enabled() && $form?->route_name,
            'elapsedDays' => $afterEvents->elapsedDays(),
            'semester' => $window['semester'],
            'deadline' => $window['end'],
            'multipleOrganizations' => count($organizationIds) > 1,
        ]);
    }
}
