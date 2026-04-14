<?php

namespace App\Http\Controllers;

use App\Helpers\ProfileMatchHelper;
use App\Models\Approval;
use App\Models\Profile;
use App\Models\Request as ActionRequest;
use App\Models\User;
use App\Services\UserProfileDataSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminController extends Controller
{
    public function profileRequests()
    {
        $requests = ActionRequest::query()
            ->where('action_type', 8)
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

        $rows = $requests
            ->map(function (ActionRequest $actionRequest) use ($approvalMap, $userMap, $suggestedProfileMap) {
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
                        ]
                        : null,
                    'status' => $status,
                    'status_label' => ucfirst($status),
                    'can_decide' => $status === 'pending',
                ];
            })
            ->values();

        $seedProfiles = ProfileMatchHelper::search('', 20)
            ->map(fn (Profile $profile) => $this->toSearchPayload($profile));

        return view('pages.sidebar.superadmin-profile-requests', [
            'title' => 'Profile Match Requests',
            'rows' => $rows,
            'seedProfiles' => $seedProfiles,
        ]);
    }

    public function searchProfiles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = (string) ($validated['q'] ?? '');
        $limit = (int) ($validated['limit'] ?? 20);

        $profiles = ProfileMatchHelper::search($query, $limit)
            ->map(fn (Profile $profile) => $this->toSearchPayload($profile));

        return response()->json([
            'data' => $profiles,
        ]);
    }

    public function decideProfileRequest(Request $request, int $requestId): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'profile_id' => ['nullable', 'integer'],
        ]);

        $actionRequest = ActionRequest::query()
            ->where('action_type', 8)
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
    private function toSearchPayload(Profile $profile): array
    {
        return [
            'profile_id' => (int) $profile->profile_id,
            'name' => trim(implode(' ', array_filter([
                $profile->first_name,
                $profile->middle_name,
                $profile->last_name,
            ]))),
            'occupation' => (string) $profile->occupation,
        ];
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
