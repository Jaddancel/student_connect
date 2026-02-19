<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\SuperAdministrator;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $profile = Profile::factory()->create([
                'user_id' => $user->user_id,
            ]);

            $user->update([
                'profile_id' => $profile->profile_id,
            ]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // creates the default state for the user model which creates a profile with the new user's user_id
        return [
            'user_email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'user_password' => static::$password ??= Hash::make('password'),
            // user_type_code is member from user_types table
            'user_type_code' => UserType::query()->where('user_type', 'user')->value('user_type_code') ?? 0,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        // edits the definition of the model to set the user_type_code to admin, which also creates an entry to admin table with the user_id of the created user
        return $this->state(fn (array $attributes) => [
            'user_type_code' => UserType::query()->where('user_type', 'admin')->value('user_type_code') ?? 1,
        ]);
    }

    public function superAdmin(): static
    {
        // edits the definition of the model to set the user_type_code to super_admin
        return $this->state(fn (array $attributes) => [
            'user_type_code' => UserType::query()->where('user_type', 'super_admin')->value('user_type_code') ?? 2,
        ])->afterCreating(function (User $user): void {
            SuperAdministrator::query()->firstOrCreate([
                'user' => $user->user_id,
            ]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
