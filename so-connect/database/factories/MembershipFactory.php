<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // get random user id and organization id
            'user_id' => \App\Models\User::inRandomOrder()->first()->id,
            'organization_id' => \App\Models\Organization::inRandomOrder()->first()->organization_id,
            'approval_id' => \App\Models\Approval::inRandomOrder()->first()->approval_id,
            'role_code' => $this->faker->numberBetween(1, 5)
            // Membership Codes
            // 00 - Non-member
            // 01 - Lower Official
            // 02 - Secretary
            // 03 - President
            // 04 - Administrator
            // 05 - Super Admin
        ];
    }

    public function unapproved()
    {
        return $this->state(function (array $attributes) {
            return [
                'approval_id' => null
            ];
        });
    }

}
