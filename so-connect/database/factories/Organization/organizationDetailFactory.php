<?php

namespace Database\Factories\Organization;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Organization\organizationDetail>
 */
class organizationDetailFactory extends Factory
{
     protected $model = \App\Models\Organization\organizationDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_name' => $this->faker->company(),
            'organization_initials' => $this->faker->lexify('???'),
            'president' => null,
        ];
    }
}
