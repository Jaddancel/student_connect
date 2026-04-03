<?php

namespace App\Http\Controllers;

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
}
