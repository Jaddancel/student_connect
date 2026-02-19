<?php

namespace Database\Factories;

use App\Models\Organization\OrganizationType;
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
            'president' => null,
            'organization_name' => $this->faker->company(),
            'organization_type' => OrganizationType::inRandomOrder()->first()->organization_type_code,
            'organization_initials' => $this->faker->lexify('???'),
        ];
    }
}
