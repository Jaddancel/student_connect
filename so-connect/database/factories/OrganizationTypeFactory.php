<?php

namespace Database\Factories;

use App\Models\organization_type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\organization_type>
 */
class OrganizationTypeFactory extends Factory
{
    protected $model = organization_type::class;

    public function definition(): array
    {
        return [
            'organization_type' => $this->faker->words(2, true),
        ];
    }
}
