<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Administrator>
 */
class AdministratorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    // It also changes the role of the user_type to admin.
    public function definition(): array
    {
        return [
            'user' => UserFactory::new()->admin()->create()->user_id,
        ];
    }
}
