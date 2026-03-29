<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
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
<<<<<<< HEAD
            //
=======
            // pick a random number from 1 to 5 and assign it to organization_type
            'organization_type' => null,
            // create a organization_detail entry and assign its id to organization_detail
            'organization_detail' => OrganizationDetail::factory()->create()->getKey(),
>>>>>>> main
        ];
    }
}
