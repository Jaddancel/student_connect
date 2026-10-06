<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Event\EventDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
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
            'organization' => null,
            'creator' => null,
            'event_detail' => EventDetail::factory()->create()->getKey(),
        ];
    }
}
