<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
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
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'occupation' => fake()->randomElement(['Other', 'Student', 'Faculty']),
            'address' => \App\Models\Profile\profileAddress::factory()->create()->getKey(),
        ];
    }

    public function twentyUnassigned()
    {
        return $this->count(20);
    }
}
