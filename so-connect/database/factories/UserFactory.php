<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Profile;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'user_password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'user_type' => 3,
            'profile' => Profile::factory()->create()->profile_id,
            'profile_pending' => false,
        ];
    }

    public function orgOfficer()
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 3,
        ]);
    }

    public function admin()
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 2,
        ]);
    }

    public function superadmin()
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 1,
        ]);
    }

    public function superadminWithoutRoles()
    {
        return $this->superadmin()->afterCreating(function (User $user) {
            $memberIds = $user->memberships()->pluck('member_id');

            if ($memberIds->isNotEmpty()) {
                DB::table('organization_officers')
                    ->whereIn('member', $memberIds->all())
                    ->delete();
            }

            $user->memberships()->delete();
        });
    }

    public function tenSuperadminsWithoutRoles()
    {
        return $this->count(10)->superadminWithoutRoles();
    }

    public function adminWithoutRoles()
    {
        return $this->admin()->afterCreating(function (User $user) {
            $memberIds = $user->memberships()->pluck('member_id');

            if ($memberIds->isNotEmpty()) {
                DB::table('organization_officers')
                    ->whereIn('member', $memberIds->all())
                    ->delete();
            }

            $user->memberships()->delete();
        });
    }

    public function tenAdminsWithoutRoles()
    {
        return $this->count(10)->adminWithoutRoles();
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function whoAppliesForRequest($org)
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 3,
        ])->afterCreating(function (User $user) use ($org) {
            $userId = $user->getKey();
            Request::create([
                'user' => $userId,
                'action' => $org.'|'.$userId,
                'action_type' => 1,
            ]);
        });
    }

    public function randomOrgRequest()
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 3,
        ])->afterCreating(function (User $user) {
            $org = Organization::query()->inRandomOrder()->value('organization_id');

            if (! $org) {
                return;
            }

            $userId = $user->getKey();
            Request::create([
                'user' => $userId,
                'action' => $org.'|'.$userId,
                'action_type' => 1,
            ]);
        });
    }
}
