<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Member $member) {
            // Fallback when no organization is provided by a seeder/state.
            if ($member->organization === null) {
                $member->organization = Organization::query()->inRandomOrder()->value('organization_id')
                    ?? Organization::factory()->create()->getKey();
            }

            // Fallback user for plain Member::factory()->create().
            if ($member->user === null) {
                $member->user = User::factory()->regular()->create()->getKey();
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
<<<<<<< HEAD
            //
        ];
    }
=======
            'member_since' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'organization' => null,
            'user' => null,
            'role' => 'member',
            'approval_id' => null,
        ];
    }

    public function president()
    {
        return $this->state(function (array $attributes) {
            return [
                'user' => User::factory()->superAdmin()->create()->getKey(),
                'role' => 'president',
                'approval_id' => null,
            ];
        });
    }

    public function officer()
    {
        return $this->state(function (array $attributes) {
            return [
                'user' => User::factory()->admin()->create()->getKey(),
                'role' => 'officer',
                'approval_id' => null,
            ];
        });
    }

    public function member()
    {
        return $this->state(function (array $attributes) {
            return [
                'user' => User::factory()->regular()->create()->getKey(),
                'role' => 'member',
                'approval_id' => null,
            ];
        });
    }
>>>>>>> main
}
