<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Organization;
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
            'member_since' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'organization' => Organization::query()->inRandomOrder()->value('organization_id')
                ?? Organization::factory()->create()->getKey(),
            'user' => User::factory()->create()->getKey(),
            'role' => $this->faker->randomElement(['member', 'officer']),
            'approval_id' => null,
        ];
    }

    public function president()
    {
        return $this->state(function (array $attributes) {
            return [
                'user' => User::factory()->admin()->create()->getKey(),
                'role' => 'president',
                'approval_id' => null,
            ];
        });
    }
}
