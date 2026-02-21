<?php

namespace Database\Factories\Event;

use App\Models\Event\EventDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event\EventDetail>
 */
class EventDetailFactory extends Factory
{
    protected $model = EventDetail::class;

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
            'event_start_date' => $startDate,
            'event_end_date' => $endDate,
            'event_description_text' => $this->faker->optional()->paragraph(),
            'event_location' => $this->faker->optional()->address(),
        ];
    }
}