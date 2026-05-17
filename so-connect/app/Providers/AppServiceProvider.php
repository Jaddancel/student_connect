<?php

namespace App\Providers;

use App\Faker\FilipinoPersonProvider;
use App\Models\Officer;
use App\Models\User;
use App\Policies\RolePolicy;
use Faker\Generator as FakerGenerator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->callAfterResolving(FakerGenerator::class, function (FakerGenerator $faker) {
            $faker->addProvider(new FilipinoPersonProvider($faker));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Officer::class, RolePolicy::class);

        Gate::define('access-dashboard', function (User $user, string $dashboard): bool {
            $isPresident = $user
                ->memberships()
                ->whereHas('officers', function ($query) {
                    $query->where('role', 'president');
                })
                ->exists();

            $isOfficer = $user
                ->memberships()
                ->whereHas('officers', function ($query) {
                    $query->where('role', 'officer');
                })
                ->exists();

            return match ($dashboard) {
                'president' => $isPresident,
                'admin', 'officer' => $isOfficer || $isPresident,
                'sysadmin' => (int) $user->user_type === 2,
                default => false,
            };
        });
    }
}
