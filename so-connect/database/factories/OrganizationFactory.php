<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Organization\organizationDetail as OrganizationDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // pick a random number from 1 to 5 and assign it to organization_type
            'organization_type' => $this->faker->numberBetween(0, 3),
            // create a organization_detail entry and assign its id to organization_detail
            'organization_detail' => OrganizationDetail::factory()->create()->getKey(),
        ];
    }
}
