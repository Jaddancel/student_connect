<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Controller;
use App\Models\Organization;

class MembershipRegistration extends Controller
{
    public function view()
    {
        $organization_types = [
            // 0 => 'Academic',
            1 => 'Socio-Civic',
            2 => 'Fraternities-Sororities',
            3 => 'Religious',
            4 => 'Special Interest',
            5 => 'University-Sanctioned',
            6 => 'Student Government',
        ];

        $selectedType = request('org_type_selector');

        $organizationsQuery = Organization::query()->with('organizationDetail');

        if ($selectedType !== null && $selectedType !== '' && array_key_exists((int) $selectedType, $organization_types)) {
            $selectedTypeCode = (string) (int) $selectedType;
            $selectedTypeLabel = $organization_types[(int) $selectedType];

            $organizationsQuery->where(function ($query) use ($selectedTypeCode, $selectedTypeLabel) {
                $query->where('organization_type', $selectedTypeCode)
                    ->orWhere('organization_type', $selectedTypeLabel);
            });
        }

        $organizations = $organizationsQuery->get();

        return view('forms.membership_registration', ['organization_types' => $organization_types, 'organizations' => $organizations]);
    }
}
