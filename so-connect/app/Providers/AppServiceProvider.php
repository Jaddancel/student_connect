<?php

namespace App\Providers;

use App\Faker\FilipinoPersonProvider;
use App\Helpers\NotificationBellHelper;
use App\Helpers\OrganizationLogoHelper;
use App\Listeners\LogAuthActivity;
use App\Listeners\SendLoginNotification;
use App\Models\Event as CalendarEvent;
use App\Models\Officer;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Observers\EventCreatedObserver;
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

        $this->app->bind(
            \App\Services\DocumentVision\DocumentVisionClient::class,
            \App\Services\DocumentVision\OllamaDocumentVisionClient::class,
        );
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
        Event::listen(Login::class, SendLoginNotification::class);

        CalendarEvent::observe(EventCreatedObserver::class);

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

            $user = auth()->user();
            $organizationSwitcherItems = collect();
            $activeOrganizationId = 0;

            if ($user) {
                $userId = (int) $user->getKey();
                $matches = DB::table('organization_officers as oo')
                    ->join('organizations as o', 'o.organization_id', '=', 'oo.organization')
                    ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                    ->where('oo.user', $userId)
                    ->select([
                        'o.organization_id as id',
                        DB::raw("COALESCE(od.name, 'Unknown Organization') as name"),
                    ])
                    ->orderBy('name')
                    ->get();

                $logoMap = OrganizationLogoHelper::map();
                $counts = NotificationBellHelper::organizationAlertCountsForUser($user);

                $organizationSwitcherItems = $matches->map(function ($organization) use ($logoMap, $counts) {
                    $orgName = (string) ($organization->name ?? 'Unknown Organization');
                    $logo = $logoMap[$orgName] ?? null;
                    $parts = preg_split('/\s+/', trim($orgName));
                    $initials = implode('', array_map(static fn ($part) => strtoupper(substr($part, 0, 1)), array_slice($parts, 0, 2)));

                    return [
                        'id' => (int) $organization->id,
                        'name' => $orgName,
                        'logo' => $logo,
                        'initials' => $initials !== '' ? $initials : 'ORG',
                        'alert_count' => (int) ($counts[(int) $organization->id] ?? 0),
                    ];
                })->values();

                $activeOrganizationId = (int) session('active_organization_id', $organizationSwitcherItems->first()['id'] ?? 0);
                if ($activeOrganizationId <= 0 || ! $organizationSwitcherItems->contains('id', $activeOrganizationId)) {
                    $activeOrganizationId = (int) ($organizationSwitcherItems->first()['id'] ?? 0);
                }
                session(['active_organization_id' => $activeOrganizationId]);
            }

            $view->with('newDocumentCount', $count)
                ->with('organizationSwitcherItems', $organizationSwitcherItems)
                ->with('hasMultipleOrganizations', $organizationSwitcherItems->count() > 1)
                ->with('activeOrganizationId', $activeOrganizationId);
        });

        Gate::define('access-dashboard', function (User $user, string $dashboard): bool {
            $isPresident = ! empty(OrganizationAuthorizationService::presidentOrganizationIdsForUser((int) $user->getKey()));
            $isOfficer = ! empty(OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey()));

            return match ($dashboard) {
                'president' => $isPresident,
                'admin', 'officer' => $isOfficer || $isPresident,
                'sysadmin' => (int) $user->user_type === 2,
                default => false,
            };
        });
    }
}
