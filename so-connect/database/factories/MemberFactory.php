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
    public function definition(): array
    {
        return [
            'organization' => null,
            'approval' => null,
            'user' => null,
            'member_since' => fake()->dateTimeBetween('-6 years', 'now'),
        ];
    }

    public function officer(): static
    {
        return $this->state(fn () => [
            'user' => User::factory()->orgOfficer()->create()->getKey(),
        ]);
    }
}
