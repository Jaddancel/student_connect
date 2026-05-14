<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
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
            'approval' => null,
            'user' => null,
            'member_since' => fake()->dateTimeBetween('-6 years', 'now'),
        ];
    }

    public function regularMember(): static
    {
        return $this->state(fn () => [ // Lazy enclosures exists, TMYK.
            'user' => User::factory()->regular()->create()->getKey(),
        ]);
    }

    public function officer(): static
    {
        return $this->state(fn () => [
            'user' => User::factory()->orgOfficer()->create()->getKey(),
        ]);
    }
}
