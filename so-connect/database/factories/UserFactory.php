<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Profile;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'user_password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'user_type' => null,
            'profile' => Profile::factory()->create()->profile_id,
        ];
    }

    public function regular()
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 3,
        ]);
    }

    public function admin()
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 2,
        ]);
    }

    public function superadmin()
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 1,
        ]);
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

    public function whoAppliesForRequest($org)
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 1,
        ])->afterCreating(function (User $user) use ($org) {
            $userId = $user->getKey();
            Request::create([
                'user' => $userId,
                'action' => $org,
                'action_type' => 1,
            ]);
        });
    }

    public function randomOrgRequest()
    {
        $org = Organization::query()->inRandomOrder()->first()->getKey();

        return $this->state(fn (array $attributes) => [
            'user_type' => 1,
        ])->afterCreating(function (User $user) use ($org) {
            $userId = $user->getKey();
            Request::create([
                'user' => $userId,
                'action' => $org,
                'action_type' => 1,
            ]);
        });
    }
}
