<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class Dashboard extends Controller
{
    //
    public function viewDashboard()
    {
        $user = auth()->user();

        $isUserAnOfficer = $user
            ->memberships()
            ->whereHas('officers')
            ->exists() ?? auth()->user()->user_type == 2 ? true : false;

        if ($isUserAnOfficer) {
            $isPresident = $user
                ->memberships()
                ->whereHas('officers', function ($query) {
                    $query->where('role', 'president');
                })
                ->exists();

            if ($isPresident) {
                return redirect()->route('president-dashboard');
            } else {
                return redirect()->route('admin-dashboard');
            }
        } else {
            return redirect()->route('member-dashboard');
        }
    }

    public function memberDashboard()
    {
        $user = auth()->user();

        $organizationIds = DB::table('members')
            ->where('user', (int) $user->getKey())
            ->pluck('organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values();

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

        return view('pages.dashboard.member', [
            'title' => 'Member Dashboard',
            'upcomingEvents' => $upcomingEvents,
        ]);
    }
}
