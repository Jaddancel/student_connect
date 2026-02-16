<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\organization_type;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // president_id is a users.user_id FK; we'll ensure this user is also an admin member of the organization in configure()
            'president_id' => User::factory(),
            // get one from database
            'organization_type_code' => organization_type::query()->inRandomOrder()->first()->getKey(),
            'organization_name' => $this->faker->company(),
            'organization_initials' => strtoupper($this->faker->lexify('???')),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Organization $organization) {
            Member::factory()
                ->admin()
                ->create([
                    'user_id' => $organization->president_id,
                    'organization_id' => $organization->organization_id,
                ]);
        });
    }
}
