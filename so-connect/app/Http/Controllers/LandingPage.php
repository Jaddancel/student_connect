<?php

namespace App\Http\Controllers;

use App\Models\Organization;

class LandingPage extends Controller
{
    public function view()
    {
        $organizationTypes = [
            1 => 'Socio-Civic',
            2 => 'Fraternities-Sororities',
            3 => 'Religious',
            4 => 'Special Interest',
            5 => 'University-Sanctioned',
            6 => 'Student Government',
        ];
        $organizations = Organization::query()->with('organizationDetail')->get();
        $organizations = $organizations->map(function ($organization) {
            return [
                'name' => $organization->organizationDetail->organization_name,
                'type' => $organization->organization_type,
            ];
        });

        // Group organizations by type
        $organizationsByType = [];
        foreach ($organizations as $organization) {
            $type = $organization['type'];
            if (! isset($organizationsByType[$type])) {
                $organizationsByType[$type] = [];
            }
            $organizationsByType[$type][] = $organization;
        }

        return view('landingPage.landingpage', compact('organizationsByType', 'organizationTypes'));
    }
}
