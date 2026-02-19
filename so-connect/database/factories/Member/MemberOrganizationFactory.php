<?php

namespace Database\Factories\Member;

use App\Models\Member\MemberOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member\MemberOrganization>
 */
class MemberOrganizationFactory extends Factory
{
    protected $model = MemberOrganization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
        ];
    }

    public function withOrganization(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'organization_id' => \App\Models\Organization::query()->inRandomOrder()->value('organization_id'),
            ];
        });
    }
}
