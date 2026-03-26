<?php

namespace Database\Factories\Organization;

use App\Models\Organization\organizationDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<organizationDetail>
 */
class organizationDetailFactory extends Factory
{
    protected $model = organizationDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_name' => $this->faker->company(),
            'organization_initials' => $this->faker->lexify('???'),
            'president' => null,
        ];
    }

    public function predefinedOrganization(): static
    {
        return $this->state(function (array $attributes) {
            return [
                // pick a random organization name from a predefined list
                'organization_name' => $this->faker->randomElement([
                    'Buklod-Lahi',
                    'Ecological and Solid Waste Management Society',
                    '3D Sighters',
                    'TAU Bulalayaw',
                    'Ladies Dormitory Organization',
                    'Men\'s Dormitory Organization',
                    'Mulat TAU Deabte Society',
                    'Passion, Rhythm, Inspiration, Melody, Excellence (PRIME)',
                    'Ranchers\' Club Philippines - TAU Chapter',
                    'Rodeo Club',
                    'TAU-Global Ambassadors',
                    'Veterinary Student Achiever\'s Society',
                    'Philippine Consortium for Science, Mathematics, and Technology',
                    'Campus Mover\'s For Christ',
                    'Christian Brotherhood International-TAU Chapter',
                    'Christian Youth for Nation',
                    'Latter-Day Saint Student Association',
                    'Student Catholic Action of the Philippines-TAU Unit',
                    'Alpha Phi Omega',
                    'Alpha Kappa RHO',
                    'TAU Gamma Phi/Sigma',
                    'Gamma Sigma Scorpions (Vermilliom Chapter)',
                    'United Ilocandia',
                    'Venerable Knight Veterinarians/Venerable Lady Veterinarians',
                ]),
                'president' => null,
            ];
        });
    }
}
