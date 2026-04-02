<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Officer;
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
            'member' => null,
            'role' => null,
            'yearterm' => YearTerm::query()->inRandomOrder()->first()?->getKey(),
            'organization' => null,
        ];
    }

    public function assignedToOrganization($orgKey): static
    {
        return $this->state(fn () => [
            'member' => Member::factory()->officer()->create([
                'organization' => $orgKey,
            ])->getKey(),
            'role' => 'officer',
            'organization' => $orgKey,
        ]);
    }

    public function toLeadOrganization($orgKey): static
    {
        return $this->state(fn () => [
            'member' => Member::factory()->officer()->create([
                'organization' => $orgKey,
            ])->getKey(),
            'role' => 'president',
            'organization' => $orgKey,
        ]);
    }
}
