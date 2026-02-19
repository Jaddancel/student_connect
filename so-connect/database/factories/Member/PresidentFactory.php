<?php

namespace Database\Factories\Member;

use App\Models\Member;
use App\Models\Member\MemberDetail;
use App\Models\Member\MemberOrganization;
use App\Models\Member\President;
use App\Models\Organization;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member\President>
 */
class PresidentFactory extends Factory
{
    protected $model = President::class;

    protected $member_detail_id;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = AdministratorFactory::new()->create()->user;
        $member = Member::factory()->asPresident()->create([
            'user' => $user,
        ]);

        return [
            'member' => $member->member_id,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (President $president) {
            $member = Member::query()->findOrFail($president->member);

            $organization = Organization::factory()->create([
                'president' => $member->member_id,
            ]);

            $memberOrganization = MemberOrganization::factory()->create([
                'organization_id' => $organization->organization_id,
            ]);

            MemberDetail::query()
                ->where('member_detail_id', $member->member_detail)
                ->update([
                    'member_organization' => $memberOrganization->member_organization_id,
                ]);
        });
    }
}
