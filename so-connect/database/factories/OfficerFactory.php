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
            'position'     => 'Others',
            'organization' => $orgKey,
        ]);
    }

    public function toLeadOrganization($orgKey): static
    {
        return $this->state(fn () => [
            'user'         => User::factory()->create()->getKey(),
            'role'         => 'president',
            'position'     => 'President',
            'organization' => $orgKey,
        ]);
    }

    public function asPresident($orgKey): static
    {
        return $this->toLeadOrganization($orgKey)->state(['position' => 'President']);
    }

    public function asAuditor($orgKey): static
    {
        return $this->assignedToOrganization($orgKey)->state(['position' => 'Auditor']);
    }

    public function asSecretary($orgKey): static
    {
        return $this->assignedToOrganization($orgKey)->state(['position' => 'Secretary']);
    }

    public function asTreasurer($orgKey): static
    {
        return $this->assignedToOrganization($orgKey)->state(['position' => 'Treasurer']);
    }
}
