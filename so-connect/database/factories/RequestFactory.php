<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Request>
 */
class RequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */    public function definition(): array
    {
        return [

        ];
    }

    public function membershipRequest()
    {
        return $this->state(function (array $attributes) {
            return [
                'action_id' => \App\Models\Membership::whereNull('approval_id')->doesntHave('requests')->inRandomOrder()->value('membership_id'),
                'action_type' => 'membership'          
            ];
        });
    }
}
