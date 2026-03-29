<?php

namespace Database\Factories;

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
    public function definition(): array {}

<<<<<<< HEAD
    public function admin() {}
=======
    public function admin()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_type' => 2,
            ];
        });
    }

    public function superAdmin()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_type' => 1,
            ];
        });
    }

    public function regular()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_type' => 3,
            ];
        });
    }
>>>>>>> main
}
