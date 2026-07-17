<?php

namespace App\Providers;

use App\Faker\FilipinoPersonProvider;
use App\Listeners\LogAuthActivity;
use App\Models\Officer;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\OrganizationAuthorizationService;
use Faker\Generator as FakerGenerator;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        // Serve every URL (assets, redirects, cookies) over HTTPS in production,
        // or wherever FORCE_HTTPS is enabled, so the site is not HTTP-only.
        if ($this->app->environment('production') || filter_var(env('FORCE_HTTPS'), FILTER_VALIDATE_BOOLEAN)) {
            URL::forceScheme('https');
        }

        Event::listen([Login::class, Logout::class], LogAuthActivity::class);

        Gate::policy(Officer::class, RolePolicy::class);

        View::composer('*', function (\Illuminate\View\View $view) {
            $count = once(function () {
                $user = auth()->user();
                if (! $user) {
                    return 0;
                }

                $userId = (int) $user->getKey();
                $isAdmin = in_array((int) $user->user_type, [1, 2], true);
                $since = now()->subHours(24);

                $query = DB::table('generated_documents as gd')
                    ->join('form_submissions as fs', 'fs.form_submission_id', '=', 'gd.form_submission_id')
                    ->where('gd.status', 'generated')
                    ->where('gd.generated_at', '>=', $since);

                if ($user->documents_last_seen_at) {
                    $query->where('gd.generated_at', '>', $user->documents_last_seen_at);
                }

                if (! $isAdmin) {
                    $orgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
                    if (empty($orgIds)) {
                        return 0;
                    }
                    $query->whereIn('fs.organization_id', $orgIds);
                }

                return (int) $query->count();
            });

            $view->with('newDocumentCount', $count);
        });

        Gate::define('access-dashboard', function (User $user, string $dashboard): bool {
            $isPresident = $user->officers()->where('role', 'president')->exists();
            $isOfficer = $user->officers()->whereIn('role', ['officer', 'president'])->exists();

            return match ($dashboard) {
                'president' => $isPresident,
                'admin', 'officer' => $isOfficer || $isPresident,
                'sysadmin' => (int) $user->user_type === 2,
                default => false,
            };
        });
    }
}
