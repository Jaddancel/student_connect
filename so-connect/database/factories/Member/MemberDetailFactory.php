<?php

namespace Database\Factories\Member;

use App\Models\Member\MemberDetail;
use App\Models\Member\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member\MemberDetail>
 */
class MemberDetailFactory extends Factory
{
    protected $model = MemberDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_organization' => null,
            'member_since' => $this->faker->dateTime(),
            'role' => Role::where('role_name', 'member')->first()->role_code,
        ];
    }

    public function withOrganization(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'member_organization' => MemberOrganizationFactory::new()->withOrganization()->create()->member_organization_id,
            ];
        });
    }

    public function asPresident(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'member_organization' => null,
                'role' => Role::where('role_name', 'president')->first()->role_code,
            ];
        });
    }
}
