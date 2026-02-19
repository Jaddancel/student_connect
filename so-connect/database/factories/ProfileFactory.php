<?php

namespace Database\Factories;

use App\Models\Profile\Occupation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional(0.7)->firstName(),
            'last_name' => $this->faker->lastName(),
            'occupation_code' => Occupation::query()->inRandomOrder()->value('occupation_code') ?? 1,
        ];
    }
}
