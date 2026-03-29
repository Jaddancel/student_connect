<?php

namespace Database\Factories\Organization;

<<<<<<< HEAD
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Organization\organizationDetail>
 */
class organizationDetailFactory extends Factory
{
     protected $model = \App\Models\Organization\organizationDetail::class;
=======
use App\Models\Organization\organizationDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<organizationDetail>
 */
class organizationDetailFactory extends Factory
{
    protected $model = organizationDetail::class;
>>>>>>> 38779d9f7f289501ec430fe173a943e7552a93e4

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
<<<<<<< HEAD
=======

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
                    'Mulat TAU Deabate Society',
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
>>>>>>> 38779d9f7f289501ec430fe173a943e7552a93e4
}
