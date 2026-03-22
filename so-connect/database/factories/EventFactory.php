<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Member;
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
        $creator = Member::query()->inRandomOrder()->first() ?? Member::factory()->create();

        return [
            // creator is a random member from the db, and the organization is the organization of that member
            'creator' => $creator->getKey(),
            'organization' => $creator->organization,
            'event_detail' => Event\eventDetails::factory()->create()->getKey(),
        ];
    }
}
