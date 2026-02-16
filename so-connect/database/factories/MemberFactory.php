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
            'organization_id' => \App\Models\Organization::factory(),
        ];
    }
    
    public function admin()
    {
        return $this->state(function (array $attributes) {
            $adminRoleCode = role::query()->firstOrCreate(['role' => 'admin'])->getKey();
            return [
                'role_code' => $adminRoleCode,
            ];
        });
    }
}
