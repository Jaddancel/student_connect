<?php

namespace Database\Factories\Event;

use App\Models\Event\EventDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventDetail>
 */
class EventDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 30));
        $end = (clone $start)->addDays(fake()->numberBetween(1, 7));

        return [
            'name' => fake()->sentence(3),
            'desc_text' => fake()->text(),
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s'),
            'location' => fake()->address(),
        ];
    }
}
