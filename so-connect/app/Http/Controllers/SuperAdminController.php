<?php

namespace App\Http\Controllers;

use App\Helpers\ProfileMatchHelper;
use App\Models\Approval;
use App\Models\DashboardWidget;
use App\Models\Profile;
use App\Models\Profile\profileAddress;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Services\UserProfileDataSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminController extends Controller
{
    public function profileRequests()
    {
        $profileRequestTypeId = (int) app(\App\Services\RequestTypeService::class)
            ->resolveSystemType(
                RequestType::SYSTEM_KEY_PROFILE_MATCH,
                'Profile Match Request',
                RequestType::CATEGORY_ROLE_SECURITY,
                null,
            )
            ->getKey();

        $requests = ActionRequest::query()
            ->where(function ($query) use ($profileRequestTypeId) {
                $query->where('action_type', 9)
                    ->orWhere('request_type_id', $profileRequestTypeId);
            })
            ->orderByDesc('requested_at')
            ->limit(300)
            ->get(['request_id', 'action', 'user', 'requested_at']);

        $approvalMap = Approval::query()
            ->whereIn('request', $requests->pluck('request_id')->all())
            ->get(['request', 'is_rejected'])
            ->keyBy('request');

        $userMap = User::query()
            ->whereIn('user_id', $requests->pluck('user')->filter()->unique()->all())
            ->get(['user_id', 'user_email', 'profile'])
            ->keyBy('user_id');

        $suggestedProfileIds = $requests
            ->map(function (ActionRequest $actionRequest) {
                [, , , , $suggestedProfileId] = $this->parseProfileRequestAction($actionRequest->action);

                return $suggestedProfileId;
            })
            ->filter(fn (int $profileId) => $profileId > 0)
            ->unique()
            ->values();

        $suggestedProfileMap = Profile::query()
            ->whereIn('profile_id', $suggestedProfileIds->all())
            ->get(['profile_id', 'first_name', 'middle_name', 'last_name', 'occupation'])
            ->keyBy('profile_id');

        $associatedSuggestedProfileIds = $this->associatedProfileIdLookup($suggestedProfileMap->values());

        $rows = $requests
            ->map(function (ActionRequest $actionRequest) use ($approvalMap, $userMap, $suggestedProfileMap, $associatedSuggestedProfileIds) {
                [$targetUserId, $firstName, $lastName, $middleName, $suggestedProfileId] = $this->parseProfileRequestAction($actionRequest->action);

                $approval = $approvalMap->get((int) $actionRequest->request_id);
                $status = $this->resolveApprovalStatus($approval);
                $user = $userMap->get($targetUserId);
                $suggestedProfile = $suggestedProfileMap->get($suggestedProfileId);

                return [
                    'request_id' => (int) $actionRequest->request_id,
                    'requested_at' => $actionRequest->requested_at,
                    'requester_user_id' => $targetUserId,
                    'requester_email' => $user?->user_email ?? 'Unknown User',
                    'submitted_name' => trim(implode(' ', array_filter([$firstName, $middleName, $lastName]))),
                    'suggested_profile_id' => $suggestedProfileId,
                    'suggested_profile' => $suggestedProfile
                        ? [
                            'profile_id' => (int) $suggestedProfile->profile_id,
                            'name' => trim(implode(' ', array_filter([
                                $suggestedProfile->first_name,
                                $suggestedProfile->middle_name,
                                $suggestedProfile->last_name,
                            ]))),
                            'occupation' => $suggestedProfile->occupation,
                            'has_user' => isset($associatedSuggestedProfileIds[(int) $suggestedProfile->profile_id]),
                        ]
                        : null,
                    'status' => $status,
                    'status_label' => ucfirst($status),
                    'can_decide' => $status === 'pending',
                ];
            })
            ->values();

        $seedProfileResults = ProfileMatchHelper::search('', 20);
        $associatedSeedProfileIds = $this->associatedProfileIdLookup($seedProfileResults);

        $seedProfiles = $seedProfileResults
            ->map(fn (Profile $profile) => $this->toSearchPayload(
                $profile,
                isset($associatedSeedProfileIds[(int) $profile->profile_id]),
            ));

        // New-officer account-creation requests (action_type=12)
        $newOfficerRequests = ActionRequest::query()
            ->where('action_type', 12)
            ->orderByDesc('requested_at')
            ->limit(300)
            ->get(['request_id', 'action', 'payload', 'requested_at']);

        $newOfficerApprovalMap = Approval::query()
            ->whereIn('request', $newOfficerRequests->pluck('request_id')->all())
            ->get(['request', 'is_rejected'])
            ->keyBy('request');

        $newOfficerRows = $newOfficerRequests->map(function (ActionRequest $r) use ($newOfficerApprovalMap) {
            $payload = (array) ($r->payload ?? []);
            $parts   = array_map('trim', explode('|', (string) $r->action));
            $name    = trim(($parts[1] ?? '').' '.($parts[3] ?? '').' '.($parts[2] ?? ''));

            $approval = $newOfficerApprovalMap->get((int) $r->request_id);
            $status   = $this->resolveApprovalStatus($approval);

            return [
                'request_id'        => (int) $r->request_id,
                'name'              => $name !== '' ? $name : 'Unknown',
                'email'             => (string) ($payload['email'] ?? ''),
                'organization_name' => (string) ($payload['organization_name'] ?? ''),
                'organization_id'   => (int) ($payload['organization_id'] ?? 0),
                'position'          => (string) ($payload['position'] ?? ''),
                'contact_number'    => (string) ($payload['contact_number'] ?? ''),
                'course'            => (string) ($payload['course'] ?? ''),
                'year_level'        => (string) ($payload['year_level'] ?? ''),
                'age'               => (int) ($payload['age'] ?? 0),
                'sex'               => (string) ($payload['sex'] ?? ''),
                'requested_at'      => $r->requested_at,
                'status'            => $status,
                'status_label'      => ucfirst($status),
                'can_decide'        => $status === 'pending',
            ];
        })->values();

        return view('pages.sidebar.superadmin-profile-requests', [
            'title'           => 'Profile Match Requests',
            'rows'            => $rows,
            'seedProfiles'    => $seedProfiles,
            'newOfficerRows'  => $newOfficerRows,
        ]);
    }

    public function requestTypes()
    {
        $requestTypes = RequestType::query()
            ->withCount(['forms', 'requests'])
            ->with('creator')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('pages.sidebar.superadmin-request-types', [
            'title' => 'Request Types',
            'requestTypes' => $requestTypes,
            'categoryOptions' => RequestType::categoryOptions(),
        ]);
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

    public function storeRequestType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:request_types,name'],
            'category' => ['required', 'string', Rule::in(RequestType::CATEGORY_OPTIONS)],
            'system_key' => ['nullable', 'string', 'max:64', 'unique:request_types,system_key'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        RequestType::query()->create([
            'name' => trim((string) $validated['name']),
            'category' => (string) $validated['category'],
            'system_key' => trim((string) ($validated['system_key'] ?? '')) ?: null,
            'created_by' => (int) $request->user()->getKey(),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'Request type created successfully.');
    }

    public function searchProfiles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'exclude_associated' => ['nullable', 'boolean'],
        ]);

        $query = (string) ($validated['q'] ?? '');
        $limit = (int) ($validated['limit'] ?? 20);
        $excludeAssociated = (bool) ($validated['exclude_associated'] ?? false);

        $profileResults = ProfileMatchHelper::search($query, $limit);
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

    public function autoAcceptSuggestedRequests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exclude_associated' => ['nullable', 'boolean'],
        ]);

        $profileRequestTypeId = (int) app(\App\Services\RequestTypeService::class)
            ->resolveSystemType(
                RequestType::SYSTEM_KEY_PROFILE_MATCH,
                'Profile Match Request',
                RequestType::CATEGORY_ROLE_SECURITY,
                null,
            )
            ->getKey();

        $excludeAssociated = (bool) ($validated['exclude_associated'] ?? false);

        $pendingRequests = ActionRequest::query()
            ->where(function ($query) use ($profileRequestTypeId) {
                $query->where('action_type', 9)
                    ->orWhere('request_type_id', $profileRequestTypeId);
            })
            ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
            ->orderBy('request_id')
            ->get(['request_id', 'action', 'user', 'request_type_id']);

        if ($pendingRequests->isEmpty()) {
            return response()->json([
                'message' => 'No pending profile requests to auto-accept.',
                'stats' => [
                    'approved' => 0,
                    'skipped_missing_suggestion' => 0,
                    'skipped_associated_profile' => 0,
                    'skipped_invalid' => 0,
                ],
            ]);
        }

        $suggestedProfiles = $pendingRequests
            ->map(function (ActionRequest $actionRequest) {
                [, , , , $suggestedProfileId] = $this->parseProfileRequestAction($actionRequest->action);

                return $suggestedProfileId;
            })
            ->filter(fn (int $profileId) => $profileId > 0)
            ->unique()
            ->values();

        $associatedProfileIds = $suggestedProfiles->isEmpty()
            ? []
            : User::query()
                ->whereIn('profile', $suggestedProfiles->all())
                ->pluck('profile')
                ->map(fn ($profileId) => (int) $profileId)
                ->filter(fn (int $profileId) => $profileId > 0)
                ->flip()
                ->all();

        $existingProfileIds = $suggestedProfiles->isEmpty()
            ? []
            : Profile::query()
                ->whereIn('profile_id', $suggestedProfiles->all())
                ->pluck('profile_id')
                ->map(fn ($profileId) => (int) $profileId)
                ->filter(fn (int $profileId) => $profileId > 0)
                ->flip()
                ->all();

        $stats = [
            'approved' => 0,
            'skipped_missing_suggestion' => 0,
            'skipped_associated_profile' => 0,
            'skipped_invalid' => 0,
        ];

        foreach ($pendingRequests as $pendingRequest) {
            [$targetUserId, , , , $suggestedProfileId] = $this->parseProfileRequestAction($pendingRequest->action);

            if ($suggestedProfileId <= 0) {
                $stats['skipped_missing_suggestion']++;

                continue;
            }

            if (! isset($existingProfileIds[$suggestedProfileId])) {
                $stats['skipped_invalid']++;

                continue;
            }

            if ($excludeAssociated && isset($associatedProfileIds[$suggestedProfileId])) {
                $stats['skipped_associated_profile']++;

                continue;
            }

            $targetUser = User::query()->find($targetUserId);

            if (! $targetUser) {
                $stats['skipped_invalid']++;

                continue;
            }

            Approval::query()->updateOrCreate(
                ['request' => (int) $pendingRequest->request_id],
                [
                    'admin' => (int) $request->user()->getKey(),
                    'approved_at' => now(),
                    'is_rejected' => false,
                ]
            );

            $targetUser->update([
                'profile' => $suggestedProfileId,
                'profile_pending' => false,
            ]);

            $associatedProfileIds[$suggestedProfileId] = true;
            $stats['approved']++;
        }

        return response()->json([
            'message' => sprintf(
                'Auto-accept finished: %d approved, %d skipped (no suggestion), %d skipped (already linked), %d skipped (invalid).',
                $stats['approved'],
                $stats['skipped_missing_suggestion'],
                $stats['skipped_associated_profile'],
                $stats['skipped_invalid'],
            ),
            'stats' => $stats,
        ]);
    }

    public function decideOfficerAccountRequest(Request $request, int $requestId): JsonResponse
    {
        $actionRequest = ActionRequest::query()
            ->where('action_type', 12)
            ->findOrFail($requestId);

        $validated = $request->validate([
            'decision'  => ['required', 'string', Rule::in(['approve', 'reject'])],
            'country'   => ['required_if:decision,approve', 'nullable', 'string', 'max:255'],
            'province'  => ['required_if:decision,approve', 'nullable', 'string', 'max:255'],
            'town'      => ['required_if:decision,approve', 'nullable', 'string', 'max:255'],
            'barangay'  => ['required_if:decision,approve', 'nullable', 'string', 'max:255'],
        ]);

        if ($validated['decision'] === 'reject') {
            Approval::query()->updateOrCreate(
                ['request' => $requestId],
                ['admin' => (int) $request->user()->getKey(), 'approved_at' => now(), 'is_rejected' => true],
            );

            return response()->json(['message' => 'Officer account request rejected.']);
        }

        $payload = (array) ($actionRequest->payload ?? []);

        DB::transaction(function () use ($validated, $payload, $requestId, $request) {
            $addr = profileAddress::create([
                'country'  => $validated['country'],
                'province' => $validated['province'],
                'town'     => $validated['town'],
                'barangay' => $validated['barangay'],
            ]);

            $profile = Profile::create([
                'first_name'              => (string) ($payload['first_name'] ?? ''),
                'middle_name'             => (string) ($payload['middle_name'] ?? ''),
                'last_name'               => (string) ($payload['last_name'] ?? ''),
                'contact_number'          => (string) ($payload['contact_number'] ?? ''),
                'age'                     => (int) ($payload['age'] ?? 0),
                'sex'                     => (string) ($payload['sex'] ?? ''),
                'religion'                => (string) ($payload['religious_affiliation'] ?? ''),
                'nationality'             => (string) ($payload['nationality'] ?? ''),
                'birthday'                => (string) ($payload['birthday'] ?? ''),
                'birthplace'              => (string) ($payload['birthplace'] ?? ''),
                'course_year'             => trim(($payload['course'] ?? '').' - '.($payload['year_level'] ?? '')),
                'occupation'              => 'Student',
                'address'                 => (int) $addr->profile_address_id,
                'position'                => (string) ($payload['position'] ?? ''),
                'photo'                   => (string) ($payload['photo'] ?? ''),
                'home_address'            => (string) ($payload['home_address'] ?? ''),
                'parents_guardian'        => (string) ($payload['parents_guardian'] ?? ''),
                'talents_hobbies'         => (string) ($payload['talents_hobbies'] ?? ''),
                'financial_support'       => $payload['financial_support'] ?? [],
                'scholar_provider'        => (string) ($payload['scholar_provider'] ?? ''),
                'financial_support_other' => (string) ($payload['others_specify'] ?? ''),
            ]);

            $user = User::create([
                'user_email'    => (string) ($payload['email'] ?? ''),
                'user_password' => 'tAU100!!',
                'user_type'     => 3,
                'profile'       => (int) $profile->profile_id,
                'profile_pending' => false,
            ]);

            $orgId = (int) ($payload['organization_id'] ?? 0);

            $approvalRecord = Approval::query()->updateOrCreate(
                ['request' => $requestId],
                ['admin' => (int) $request->user()->getKey(), 'approved_at' => now(), 'is_rejected' => false],
            );

            DB::table('organization_officers')->insert([
                'user'          => (int) $user->user_id,
                'approval'      => (int) $approvalRecord->approval_id,
                'organization'  => $orgId,
                'role'          => 'officer',
                'yearterm'      => null,
                'member_since'  => now(),
                'registered_at' => now(),
                'reassigned_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Officer account created successfully.']);
    }

    public function decideProfileRequest(Request $request, int $requestId): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'profile_id' => ['nullable', 'integer'],
        ]);

        $profileRequestTypeId = (int) app(\App\Services\RequestTypeService::class)
            ->resolveSystemType(
                RequestType::SYSTEM_KEY_PROFILE_MATCH,
                'Profile Match Request',
                RequestType::CATEGORY_ROLE_SECURITY,
                null,
            )
            ->getKey();

        $actionRequest = ActionRequest::query()
            ->where(function ($query) use ($profileRequestTypeId) {
                $query->where('action_type', 9)
                    ->orWhere('request_type_id', $profileRequestTypeId);
            })
            ->findOrFail($requestId);

        [$targetUserId, , , , $suggestedProfileId] = $this->parseProfileRequestAction($actionRequest->action);

        $targetUser = User::query()->find($targetUserId);

        if (! $targetUser) {
            return response()->json([
                'message' => 'The request target user no longer exists.',
            ], 422);
        }

        $selectedProfileId = $validated['profile_id'] ?? null;

        if ($validated['decision'] === 'approve') {
            $selectedProfileId = (int) ($selectedProfileId ?: $suggestedProfileId);

            if ($selectedProfileId <= 0 || ! Profile::query()->where('profile_id', $selectedProfileId)->exists()) {
                return response()->json([
                    'message' => 'Select a valid profile before approving.',
                ], 422);
            }
        }

        Approval::query()->updateOrCreate(
            ['request' => (int) $actionRequest->getKey()],
            [
                'admin' => (int) $request->user()->getKey(),
                'approved_at' => now(),
                'is_rejected' => $validated['decision'] === 'reject',
            ]
        );

        if ($validated['decision'] === 'approve') {
            $targetUser->update([
                'profile' => (int) $selectedProfileId,
                'profile_pending' => false,
            ]);
        } else {
            $targetUser->update([
                'profile_pending' => false,
            ]);
        }

        return response()->json([
            'message' => $validated['decision'] === 'approve'
                ? 'Profile request approved and linked successfully.'
                : 'Profile request rejected.',
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

        $profile->update($validated);

        return redirect()
            ->route('superadmin.profiles.edit', $id)
            ->with('status', 'Profile updated successfully.');
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
     * @return array{0:int,1:string,2:string,3:string,4:int}
     */
    private function parseProfileRequestAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 5) {
            return [0, '', '', '', 0];
        }

        $userId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $firstName = $parts[1];
        $lastName = $parts[2];
        $middleName = $parts[3];
        $suggestedProfileId = ctype_digit($parts[4]) ? (int) $parts[4] : 0;

        return [$userId, $firstName, $lastName, $middleName, $suggestedProfileId];
    }

    private function resolveApprovalStatus(?Approval $approval): string
    {
        if (! $approval) {
            return 'pending';
        }

        return $approval->is_rejected ? 'rejected' : 'approved';
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
