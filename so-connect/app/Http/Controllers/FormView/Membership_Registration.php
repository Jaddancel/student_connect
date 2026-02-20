<?php

namespace App\Http\Controllers\FormView;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Organization\OrganizationType;
use Illuminate\Http\Request;

class Membership_Registration extends Controller
{
    public function view(Request $request)
    {
        $organization_types = OrganizationType::query()
            ->orderBy('organization_type')
            ->get(['organization_type_code', 'organization_type']);

        $organizations = Organization::query()
            ->when($request->org_type_selector, function ($query, $type) {
                return $query->where('organization_type', $type);
            })
            ->orderBy('organization_id')
            ->get(['organization_id', 'organization_name']);

        return view('forms.membership_registration', compact('organizations', 'organization_types'));
    }

    public function index(Request $request)
    {
        $organization_types = OrganizationType::all();
        $organizations = Organization::when($request->org_type_selector, function ($query, $type) {
            return $query->where('organization_type', $type);
        })
            ->get();

        return view('forms.membership_registration', compact('organization_types', 'organizations'));
    }
}
