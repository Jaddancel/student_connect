<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Member;
use App\Models\Officer;
use App\Models\Organization;
use App\Models\Organization\OrganizationDetail;
use App\Models\Post;
use App\Models\Request;
use App\Models\User;
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

                Post::factory()->count(6)->forOrganization($organizationId)->create();
                Post::factory()->count(2)->featured()->forOrganization($organizationId)->create();

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
                    })->create();

                $event_org = $organizationId;
                $name = fake()->company();
                $desc_text = fake()->text();
                $start = now()->addDays(fake()->numberBetween(1, 30));
                $end = (clone $start)->addDays(fake()->numberBetween(1, 7));
                $start_time = $start->format('Y-m-d H:i:s');
                $end_time = $end->format('Y-m-d H:i:s');
                $location = fake()->address();

                $name = substr(str_replace('|', '/', $name), 0, 40);
                $desc_text = substr(str_replace(['|', "\r", "\n"], ['/', ' ', ' '], $desc_text), 0, 90);
                $location = substr(str_replace(['|', "\r", "\n"], ['/', ' ', ' '], $location), 0, 40);
                $action = $event_org.'|'.$name.'|'.$desc_text.'|'.$start_time.'|'.$end_time.'|'.$location;

                Request::factory()->count(4)->state(function () use ($action) {
                    return [
                        'user' => Officer::query()->inRandomOrder()->first()->getKey(),
                        'action' => $action,
                        'requested_at' => now(),
                        'action_type' => 2,
                    ];
                })->create();

                User::factory()->count(5)->whoAppliesForRequest($organizationId)->create();
            });
    }
}
