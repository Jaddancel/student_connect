<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::factory()->count(10)->create();

        foreach ($organizations as $organization) {
            $president = Member::factory()->president()->create([
                'organization' => $organization->getKey(),
            ]);

            $organization->organizationDetail?->update([
                'president' => $president->getKey(),
            ]);
        }
    }
}
