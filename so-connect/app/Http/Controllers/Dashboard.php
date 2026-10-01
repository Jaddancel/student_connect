<?php

namespace App\Http\Controllers;

use App\Services\OrganizationAuthorizationService;
use Illuminate\Support\Facades\DB;

class Dashboard extends Controller
{
    public function viewDashboard()
    {
        $user = auth()->user();

        $userType = (int) $user->user_type;

        // Sign-up applicant awaiting approval: they hold no membership, so the
        // officer dashboard would be empty — send them to their request status.
        if ($user->isGuest()) {
            return redirect()->route('guest.dashboard');
        }

        if (! $user->profile && ! $user->profile_pending) {
            return redirect()->route('profile.create');
        }

        // Super admin: reuse the existing monitoring-dashboard data builder.
        if ($userType === 1) {
            return app(SuperAdminController::class)->monitoringDashboard();
        }

        // Admin.
        if ($userType === 2) {
            return view('pages.dashboard.administrator', [
                'title' => 'Admin Dashboard',
                'canDecide' => false,
            ]);
        }

        return $this->officerDashboard();
    }

    public function officerDashboard()
    {
        $user = auth()->user();

        $organizationIds = collect(OrganizationAuthorizationService::memberOrganizationIdsForUser((int) $user->getKey()));

        $upcomingEvents = collect();

        if ($organizationIds->isNotEmpty()) {
            $upcomingEvents = DB::table('events as e')
                ->join('event_details as ed', 'ed.event_detail_id', '=', 'e.event_detail')
                ->leftJoin('organizations as o', 'o.organization_id', '=', 'e.organization')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('e.organization', $organizationIds->all())
                ->where('ed.start_time', '>=', now())
                ->orderBy('ed.start_time')
                ->limit(5)
                ->get([
                    'e.event_id',
                    'e.organization',
                    'ed.name as event_name',
                    'ed.location as event_location',
                    'ed.start_time',
                    'ed.end_time',
                    DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
                ]);
        }

        return view('pages.dashboard.officer', [
            'title' => 'Dashboard',
            'upcomingEvents' => $upcomingEvents,
        ]);
    }
}
