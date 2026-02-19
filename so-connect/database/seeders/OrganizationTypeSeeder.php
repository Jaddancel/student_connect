<?php

namespace Database\Seeders;

use App\Models\Organization\OrganizationType;
use Illuminate\Database\Seeder;

class OrganizationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizationTypes = ['academic', 'socio-civic', 'religious', 'fraternities-sororities', 'special interest', 'university-sanctioned', 'student government'];

        foreach ($organizationTypes as $type) {
            OrganizationType::create([
                'organization_type_code' => OrganizationType::max('organization_type_code') + 1,
                'organization_type' => $type,
            ]);
        }
    }
}
