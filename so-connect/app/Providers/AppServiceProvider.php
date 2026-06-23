<?php

namespace App\Providers;

use App\Faker\FilipinoPersonProvider;
use App\Listeners\LogAuthActivity;
use App\Models\FormSubmission;
use App\Models\Officer;
use App\Models\User;
use App\Observers\FormSubmissionObserver;
use App\Policies\RolePolicy;
use App\Services\LlmService;
use App\Services\OcrService;
use App\Services\OrganizationAuthorizationService;
use App\Services\SignatureRecordService;
use Faker\Generator as FakerGenerator;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        $this->app->singleton(OcrService::class, function () {
            return new OcrService(
                (string) config('services.ocr.url', 'http://ocr:5000'),
                (int) config('services.ocr.timeout', 60),
            );
        });

        $this->app->singleton(LlmService::class, function () {
            return new LlmService(
                (string) config('services.ollama.url', 'http://ollama:11434'),
                (string) config('services.ollama.model', 'qwen3.5:9b'),
                (int) config('services.ollama.timeout', 120),
            );
        });

        $this->app->singleton(SignatureRecordService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen([Login::class, Logout::class], LogAuthActivity::class);

        FormSubmission::observe(FormSubmissionObserver::class);

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
