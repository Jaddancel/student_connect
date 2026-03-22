<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_email' => $this->faker->unique()->safeEmail(),
            'user_password' => bcrypt('password'),
            'user_created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'user_type' => $this->faker->numberBetween(1, 3),
            'profile_id' => Profile::factory()->create()->getKey(),
        ];
    }

    public function admin()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_type' => 2,
            ];
        });
    }
}
