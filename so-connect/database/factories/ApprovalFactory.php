<?php

namespace Database\Factories;

use App\Models\Approval;
use App\Models\Officer;
use App\Models\Organization;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Approval>
 */
class ApprovalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approved_at' => now(),
            'request' => null,
            'admin' => null,
            'stage' => null,
        ];
    }

    public function approveMemberships()
    {

        $randomOrg = Organization::query()->inRandomOrder()->first()?->getKey();

        return $this->state(fn (array $attributes) => [
            'admin' => Officer::query()->where('organization', $randomOrg)->inRandomOrder()->first()?->getKey(),
            'request' => Request::query()
                ->where('action_type', 1)
                ->where('action', 'like', $randomOrg.'|%')
                ->inRandomOrder()
                ->first()?->getKey(),
            'is_rejected' => false,
        ])->afterCreating(function (Approval $approval) use ($randomOrg) {
            \Illuminate\Support\Facades\DB::table('organization_officers')->insert([
                'organization'  => $randomOrg,
                'user'          => User::query()->inRandomOrder()->first()->user_id,
                'approval'      => $approval->getKey(),
                'role'          => 'member',
                'member_since'  => now(),
                'registered_at' => now(),
                'reassigned_at' => now(),
            ]);
        });
    }

    public function denyMemberships()
    {

        $randomOrg = Organization::query()->inRandomOrder()->first()?->getKey();

        return $this->state(fn (array $attributes) => [
            'admin' => Officer::query()->where('organization', $randomOrg)->inRandomOrder()->first()?->getKey(),
            'request' => Request::query()
                ->where('action_type', 1)
                ->where('action', 'like', $randomOrg.'|%')
                ->inRandomOrder()
                ->first()?->getKey(),
            'is_rejected' => true,
        ])->afterCreating(function (Approval $approval) use ($randomOrg) {
            \Illuminate\Support\Facades\DB::table('organization_officers')->insert([
                'organization'  => $randomOrg,
                'user'          => User::query()->inRandomOrder()->first()->user_id,
                'approval'      => $approval->getKey(),
                'role'          => 'member',
                'member_since'  => now(),
                'registered_at' => now(),
                'reassigned_at' => now(),
            ]);
        });
    }
}
