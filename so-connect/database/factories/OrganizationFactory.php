<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Member;
use App\Models\Officer;
use App\Models\Organization;
use App\Models\Organization\OrganizationDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'detail' => null,
            'organization_type' => null,
        ];
    }

    public function makeOrganization($orgName, $orgType)
    {
        return $this->state([
            'organization_type' => $orgType,
        ])
            // ->afterMaking(function (Organization $organization) use ($orgName) {
            //     $organization->update([
            //         'detail' => OrganizationDetail::factory()->has(Officer::factory()->toLeadOrganization($organization->id))->create([
            //             'name' => $orgName,
            //         ]),
            //         'officer' => Officer::factory()->count(10)->assignedToOrganization($organization->id)->create()->getKey(),
            //     ]);
            //     Member::factory()->count(20)->make([
            //         'organization' => $organization->id,
            //     ]);
            // });
            ->afterCreating(function (Organization $organization) use ($orgName) {
                $organizationId = $organization->getKey();

                $detailId = OrganizationDetail::factory()->create([
                    'name' => $orgName,
                ])->getKey();

                Officer::factory()->toLeadOrganization($organizationId)->create();
                Officer::factory()->count(9)->assignedToOrganization($organizationId)->create();
                Member::factory()->count(20)->regularMember()->create([
                    'organization' => $organizationId,
                ]);

                $organization->update([
                    'detail' => $detailId,
                ]);

                // Create Events
                // Event::factory()->count(20)->create([
                //     'organization' => $organizationId,
                //     'creator' => Officer::take(1)->where('organization', $organizationId),
                // ]);

                Event::factory()
                    ->count(20)
                    ->state(function () use ($organizationId) {
                        return [
                            'organization' => $organizationId,
                            'creator' => Officer::where('organization', $organizationId)
                                ->inRandomOrder()->first()
                                ->getKey(),
                        ];
                    })
                    ->create();
            });
    }
}
