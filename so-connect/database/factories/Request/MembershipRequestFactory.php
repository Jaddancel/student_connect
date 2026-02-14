<?php

namespace Database\Factories\Request;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Request\MembershipRequest>
 */
class MembershipRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // pick a random membership_id that has a null approval_id
            'action_id' => \App\Models\Membership::whereNull('approval_id')->inRandomOrder()->first()->membership_id,
        ];
    }
}
