<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_name' => $this->faker->sentence(3),
            'event_description' => $this->faker->paragraph(),
            'event_start_time' => $this->faker->dateTimeBetween('+1 week', '+1 month'),
            'event_end_time' => $this->faker->dateTimeBetween('+1 month', '+2 months'),
            'membership_id' => $this->faker->numberBetween(1, 10), // Assuming you have 10 memberships in your database
            'approval_id' => null, 
        ];
    }
}
