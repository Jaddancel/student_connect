<?php

namespace App\Http\Controllers\FormView;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

class Membership_Registration extends Controller
{

    public function view()
    {
        // Note to Jad: use queries instead of ::all().     - Jad
        $organizations = Organization::query()
            ->orderBy('organization_name')
            ->get(['organization_id', 'organization_name']);
        return view('forms.membership_registration', compact('organizations'));
    }

}
