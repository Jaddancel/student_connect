<?php

namespace Database\Factories;

use App\Models\Officer;
use App\Models\User;
use App\Models\YearTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Officer>
 */
class OfficerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user'         => null,
            'role'         => null,
            'yearterm'     => YearTerm::query()->inRandomOrder()->first()?->getKey(),
            'organization' => null,
        ];
    }

    public function assignedToOrganization($orgKey): static
    {
        return $this->state(fn () => [
            'user'         => User::factory()->create()->getKey(),
            'role'         => 'officer',
            'organization' => $orgKey,
        ]);
    }

    public function toLeadOrganization($orgKey): static
    {
        return $this->state(fn () => [
            'user'         => User::factory()->create()->getKey(),
            'role'         => 'president',
            'organization' => $orgKey,
        ]);
    }
}
