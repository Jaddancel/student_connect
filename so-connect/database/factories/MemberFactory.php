<?php

namespace Database\Factories;

use App\Models\role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $memberRoleCode = role::query()->firstOrCreate(['role' => 'member'])->getKey();

        return [
            'user_id' => \App\Models\User::factory(),
            'approval_id' => null, // You can set this to a factory if you have an Approval model
            'role_code' => $memberRoleCode,
            'organization_id' => \App\Models\Organization::query()->inRandomOrder()->first()->getKey(),
        ];
    }
    
    public function president()
    {
        return $this->state(function (array $attributes) {
            $presidentRoleCode= role::query()->firstOrCreate(['role' => 'president'])->getKey();
            return [ //create a user with admin user_type
                'user_id' => \App\Models\Administrator::factory()->create()->user_id,
                'approval_id' => null, // You can set this to a factory if you have an Approval model
                'role_code' => $presidentRoleCode,
            ];
        });
    }
}
