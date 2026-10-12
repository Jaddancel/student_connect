<?php

namespace App\Http\Controllers;

use App\Helpers\ProfileMatchHelper;
use App\Models\DashboardWidget;
use App\Models\Profile;
use App\Models\User;
use App\Services\SystemDashboardService;
use App\Services\UserProfileDataSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminController extends Controller
{
    public function monitoringDashboard(SystemDashboardService $dashboard)
    {
        $period = $dashboard->normalizePeriod(request()->query('period'));

        return view('pages.dashboard.superadmin', [
            'title' => 'System Dashboard',
            'summary' => $dashboard->summary(),
            'uptimeSeconds' => $dashboard->uptimeSeconds(),
            'periodOptions' => SystemDashboardService::PERIOD_LABELS,
            'period' => $period,
            'metrics' => $dashboard->metrics($period),
        ]);
    }

    public function dashboardMetrics(Request $request, SystemDashboardService $dashboard): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', 'string', Rule::in(array_keys(SystemDashboardService::PERIOD_LABELS))],
        ]);

        return response()->json($dashboard->metrics($dashboard->normalizePeriod($validated['period'] ?? null)));
    }

    public function dashboardBuilder()
    {
        $widgets = DashboardWidget::query()
            ->with('creator')
            ->orderBy('role')
            ->orderBy('sort_order')
            ->get();

        return view('pages.sidebar.superadmin-dashboard-builder', [
            'title' => 'Dashboard Builder',
            'widgets' => $widgets,
            'roleOptions' => DashboardWidget::ROLE_OPTIONS,
            'typeOptions' => DashboardWidget::TYPE_OPTIONS,
        ]);
    }

    public function storeDashboardWidget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(DashboardWidget::ROLE_OPTIONS)],
            'widget_type' => ['required', 'string', Rule::in(DashboardWidget::TYPE_OPTIONS)],
            'title' => ['required', 'string', 'max:255'],
            'config' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'column_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $config = null;

        if (! empty($validated['config'])) {
            $decoded = json_decode((string) $validated['config'], true);

            if (! is_array($decoded)) {
                return back()->withErrors([
                    'config' => 'Widget config must be valid JSON.',
                ])->withInput();
            }

            $config = $decoded;
        }

        DashboardWidget::query()->create([
            'role' => (string) $validated['role'],
            'widget_type' => (string) $validated['widget_type'],
            'title' => trim((string) $validated['title']),
            'config' => $config,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'column_span' => (int) ($validated['column_span'] ?? 12),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => (int) $request->user()->getKey(),
        ]);

        return back()->with('success', 'Dashboard widget created successfully.');
    }

    public function updateDashboardWidget(Request $request, int $widgetId): RedirectResponse
    {
        $widget = DashboardWidget::query()->findOrFail($widgetId);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'column_span' => ['required', 'integer', 'min:1', 'max:12'],
            'is_active' => ['nullable', 'boolean'],
            'config' => ['nullable', 'string', 'max:5000'],
        ]);

        $config = $widget->config;

        if (array_key_exists('config', $validated) && trim((string) $validated['config']) !== '') {
            $decoded = json_decode((string) $validated['config'], true);

            if (! is_array($decoded)) {
                return back()->withErrors([
                    'config' => 'Widget config must be valid JSON.',
                ]);
            }

            $config = $decoded;
        }

        $widget->update([
            'title' => trim((string) $validated['title']),
            'sort_order' => (int) $validated['sort_order'],
            'column_span' => (int) $validated['column_span'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'config' => $config,
        ]);

        return back()->with('success', 'Dashboard widget updated successfully.');
    }

    public function searchProfiles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'exclude_associated' => ['nullable', 'boolean'],
            'admins' => ['nullable', 'boolean'],
        ]);

        $query = (string) ($validated['q'] ?? '');
        $limit = (int) ($validated['limit'] ?? 20);
        $excludeAssociated = (bool) ($validated['exclude_associated'] ?? false);
        $adminsOnly = (bool) ($validated['admins'] ?? false);

        $profileResults = ProfileMatchHelper::search($query, $limit, $adminsOnly);
        $associatedProfileIds = $this->associatedProfileIdLookup($profileResults);

        if ($excludeAssociated) {
            $profileResults = $profileResults
                ->reject(fn (Profile $profile) => isset($associatedProfileIds[(int) $profile->profile_id]))
                ->values();
        }

        $profileIds = $profileResults->pluck('profile_id')->map(fn ($id) => (int) $id)->all();
        $userEmailByProfileId = User::query()
            ->whereIn('profile', $profileIds)
            ->get(['profile', 'user_email'])
            ->keyBy(fn ($user) => (int) $user->profile)
            ->map(fn ($user) => $user->user_email)
            ->all();

        $profiles = $profileResults
            ->map(fn (Profile $profile) => $this->toSearchPayload(
                $profile,
                isset($associatedProfileIds[(int) $profile->profile_id]),
                $userEmailByProfileId[(int) $profile->profile_id] ?? null,
            ));

        return response()->json([
            'data' => $profiles,
        ]);
    }

    public function profiles()
    {
        return view('pages.sidebar.superadmin-profiles', ['title' => 'Profile Manager']);
    }

    public function editProfile(int $id)
    {
        $profile = Profile::findOrFail($id);
        $user = $profile->user()->first();

        if ($user && (int) $user->user_type === 1) {
            abort(403, 'Cannot edit a superadmin profile.');
        }

        return view('pages.sidebar.superadmin-profile-edit', [
            'title' => 'Edit Profile',
            'profile' => $profile,
            'linkedUser' => $user,
        ]);
    }

    public function updateProfile(Request $request, int $id)
    {
        $profile = Profile::findOrFail($id);
        $user = $profile->user()->first();

        if ($user && (int) $user->user_type === 1) {
            abort(403, 'Cannot edit a superadmin profile.');
        }

        $validated = $request->validate([
            'first_name'     => ['required', 'string', 'max:255'],
            'last_name'      => ['required', 'string', 'max:255'],
            'middle_name'    => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'digits:10', 'starts_with:9'],
            'age'            => ['nullable', 'integer', 'min:1', 'max:120'],
            'sex'            => ['nullable', 'in:Male,Female'],
            'religion'       => ['nullable', 'string', 'max:255'],
            'nationality'    => ['nullable', 'string', 'max:255'],
            'birthday'       => ['nullable', 'date', 'before:today'],
            'course_year'    => ['nullable', 'string', 'max:255'],
            'occupation'     => ['nullable', 'string', 'max:255'],
        ]);

        $validated['middle_name'] = $validated['middle_name'] ?? '';

        $profile->update($validated);

        return redirect()
            ->route('superadmin.profiles.edit', $id)
            ->with('status', 'Profile updated successfully.');
    }

    public function destroyProfile(int $id): JsonResponse
    {
        $profile = Profile::findOrFail($id);
        $user = $profile->user()->first();

        if ($user && (int) $user->user_type === 1) {
            abort(403, 'Cannot delete a superadmin profile.');
        }

        $profile->delete();

        return response()->json([
            'message' => 'Profile deleted successfully.',
        ]);
    }

    public function dataSyncPage()
    {
        return view('pages.sidebar.superadmin-data-sync', [
            'title' => 'Data Sync',
            'apiKeyConfigured' => $this->configuredApiKey() !== '',
        ]);
    }

    public function exportData(UserProfileDataSyncService $syncService): StreamedResponse
    {
        $payload = $syncService->exportPayload();
        $fileName = 'user-profile-export-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $fileName, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function importFromFile(Request $request, UserProfileDataSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:json,txt'],
        ]);

        $raw = file_get_contents($validated['import_file']->getRealPath());

        if ($raw === false) {
            return back()->withErrors([
                'import_file' => 'Unable to read the uploaded file.',
            ]);
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return back()->withErrors([
                'import_file' => 'Invalid JSON payload.',
            ]);
        }

        $stats = $syncService->importPayload($payload);

        return back()->with('success', $this->formatSyncSummary($stats));
    }

    public function importFromApi(Request $request, UserProfileDataSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'api_endpoint' => ['required', 'url', 'max:2000'],
            'api_key' => ['required', 'string', 'max:255'],
        ]);

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'X-SC-API-KEY' => $validated['api_key'],
        ])->get($validated['api_endpoint']);

        if (! $response->ok()) {
            return back()->withErrors([
                'api_endpoint' => 'Unable to fetch data from the API endpoint.',
            ]);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return back()->withErrors([
                'api_endpoint' => 'The API response is not a valid JSON object.',
            ]);
        }

        $stats = $syncService->importPayload($payload);

        return back()->with('success', $this->formatSyncSummary($stats));
    }

    public function apiExport(Request $request, UserProfileDataSyncService $syncService): JsonResponse
    {
        if (! $this->hasValidApiKey($request)) {
            return response()->json([
                'message' => 'Invalid API key.',
            ], 401);
        }

        return response()->json($syncService->exportPayload());
    }

    public function apiImport(Request $request, UserProfileDataSyncService $syncService): JsonResponse
    {
        if (! $this->hasValidApiKey($request)) {
            return response()->json([
                'message' => 'Invalid API key.',
            ], 401);
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            return response()->json([
                'message' => 'Invalid JSON payload.',
            ], 422);
        }

        $stats = $syncService->importPayload($payload);

        return response()->json([
            'message' => 'Data imported successfully.',
            'stats' => $stats,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toSearchPayload(Profile $profile, bool $hasUser = false, ?string $userEmail = null): array
    {
        return [
            'profile_id' => (int) $profile->profile_id,
            'first_name' => (string) $profile->first_name,
            'middle_name' => (string) $profile->middle_name,
            'last_name' => (string) $profile->last_name,
            'occupation' => (string) $profile->occupation,
            'course_year' => (string) $profile->course_year,
            'sex' => (string) $profile->sex,
            'origin' => (string) ($profile->origin ?? Profile::ORIGIN_REGISTERED),
            'user_email' => $userEmail,
            'has_user' => $hasUser,
        ];
    }

    /**
     * @param  Collection<int, Profile>  $profiles
     * @return array<int, bool>
     */
    private function associatedProfileIdLookup(Collection $profiles): array
    {
        $profileIds = $profiles
            ->pluck('profile_id')
            ->map(fn ($profileId) => (int) $profileId)
            ->filter(fn (int $profileId) => $profileId > 0)
            ->unique()
            ->values();

        if ($profileIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('profile', $profileIds->all())
            ->pluck('profile')
            ->map(fn ($profileId) => (int) $profileId)
            ->filter(fn (int $profileId) => $profileId > 0)
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function formatSyncSummary(array $stats): string
    {
        return sprintf(
            'Import complete. Profiles: %d created, %d updated. Users: %d created, %d updated.',
            (int) ($stats['created_profiles'] ?? 0),
            (int) ($stats['updated_profiles'] ?? 0),
            (int) ($stats['created_users'] ?? 0),
            (int) ($stats['updated_users'] ?? 0),
        );
    }

    private function configuredApiKey(): string
    {
        return trim((string) config('services.superadmin_data_sync.key', ''));
    }

    private function hasValidApiKey(Request $request): bool
    {
        $configuredApiKey = $this->configuredApiKey();

        if ($configuredApiKey === '') {
            return false;
        }

        $providedApiKey = trim((string) ($request->header('X-SC-API-KEY') ?: $request->input('api_key', '')));

        if ($providedApiKey === '') {
            return false;
        }

        return hash_equals($configuredApiKey, $providedApiKey);
    }
}
