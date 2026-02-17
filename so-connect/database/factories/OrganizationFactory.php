<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Organization;
use App\Models\President;
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
            // get one from database
                'president_id' => null, // Create a president and use its member_id as president_id
            'organization_type_code' => organization_type::query()->inRandomOrder()->first()->getKey(),
            'organization_name' => $this->faker->company(),
            'organization_initials' => strtoupper($this->faker->lexify('???')),
        ];
    }


    public function has_president(){
        return $this->state(function (array $attributes) {
            return [
                'president_id' => President::factory()->create()->member_id, // Create a president and use its member_id as president_id
            ];
        });
    }
//    public function configure(): static
//    {
//        return $this->afterCreating(function (Organization $organization) {
//            President::factory()
//                ->create([
//                    'member_id' => Member::factory()
//                        ->president()
//                        ->create([
//                            'organization_id' => $organization->getKey(),
//                        ])->getKey(),
//                ]);
//        });
//    }
}
