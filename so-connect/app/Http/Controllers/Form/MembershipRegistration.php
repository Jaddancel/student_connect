<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Controller;
use App\Models\Organization;

class MembershipRegistration extends Controller
{
    public function view()
    {
        $organization_types = [
            0 => 'College',
            1 => 'Club',
            2 => 'Fraternity/Sorority',
            3 => 'Other',
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
