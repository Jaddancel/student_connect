<?php

namespace Database\Factories;

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
            // first_name, middle_name (which can be null), and last_name are required fields in the database.
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->boolean(70) ? $this->faker->firstName() : null, // 70% chance of having a middle name
            'last_name' => $this->faker->lastName(),
            'occupation_code' => \App\Models\occupation::query()->inRandomOrder()->first()->getKey(),
        ];
    }
}
