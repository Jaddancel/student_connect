<?php

namespace Database\Factories;

use App\Models\Member;
use Database\Factories\Member\MemberDetailFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // create member_detail item
            'user' => fn () => \App\Models\User::inRandomOrder()->first()->user_id,
            'member_detail' => fn () => MemberDetailFactory::new()->withOrganization()->create()->member_detail_id,
            'approval_id' => fn () => ApprovalFactory::new()->create()->approval_id,
        ];
    }

    public function asPresident(): static
    {
        // Creates member_detail item with president role, creates a admin item for the user assigned to the member item, and changes the user_type of the user assigned to the member item to admin
        return $this->state(function (array $attributes) {
            return [
                'member_detail' => MemberDetailFactory::new()->asPresident()->create()->member_detail_id,
            ];
        });
    }
}
