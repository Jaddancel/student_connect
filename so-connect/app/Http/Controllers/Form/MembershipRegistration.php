<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Controller;
use App\Models\Organization;

class MembershipRegistration extends Controller
{
    public function view()
    {
        $organization_types = ['College', 'Club', 'Fraternity/Sorority', 'Other'];
        $organizations = Organization::all();

        return view('forms.membership_registration', ['organization_types' => $organization_types, 'organizations' => $organizations]);
    }
}
