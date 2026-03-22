<?php

namespace Database\Factories\Event;

use App\Models\Event\eventDetails;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<eventDetails>
 */
class eventDetailsFactory extends Factory
{
    protected $model = eventDetails::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('+1 week', '+6 months');
        $endDate = $this->faker->dateTimeBetween($startDate, (clone $startDate)->modify('+3 days'));

        return [
            'event_name' => $this->faker->sentence(3),
            'event_start_time' => $startDate,
            'event_end_time' => $endDate,
            'event_desc_text' => $this->faker->optional()->paragraph(),
            'event_location' => $this->faker->optional()->address(),
        ];
    }
}
