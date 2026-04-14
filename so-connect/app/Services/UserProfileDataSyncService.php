<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserProfileDataSyncService
{
    /**
     * @return array<string, mixed>
     */
    public function exportPayload(): array
    {
        $profiles = Profile::query()
            ->orderBy('profile_id')
            ->get(['profile_id', 'first_name', 'middle_name', 'last_name', 'occupation'])
            ->map(function (Profile $profile) {
                return [
                    'profile_id' => (int) $profile->profile_id,
                    'first_name' => (string) $profile->first_name,
                    'middle_name' => (string) $profile->middle_name,
                    'last_name' => (string) $profile->last_name,
                    'occupation' => (string) $profile->occupation,
                ];
            })
            ->values();

        $users = User::query()
            ->with('profile:profile_id,first_name,middle_name,last_name,occupation')
            ->orderBy('user_id')
            ->get(['user_id', 'user_email', 'user_password', 'user_type', 'profile', 'user_created_at'])
            ->map(function (User $user) {
                return [
                    'user_id' => (int) $user->user_id,
                    'user_email' => (string) $user->user_email,
                    'user_password' => (string) $user->user_password,
                    'user_type' => (int) $user->user_type,
                    'profile_id' => $user->profile ? (int) $user->profile : null,
                    'profile' => $user->profile
                        ? [
                            'first_name' => (string) $user->profile->first_name,
                            'middle_name' => (string) $user->profile->middle_name,
                            'last_name' => (string) $user->profile->last_name,
                            'occupation' => (string) $user->profile->occupation,
                        ]
                        : null,
                    'user_created_at' => $user->user_created_at ? (string) $user->user_created_at : null,
                ];
            })
            ->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'profiles' => $profiles,
            'users' => $users,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    public function importPayload(array $payload): array
    {
        $profilesPayload = collect(Arr::get($payload, 'profiles', []));
        $usersPayload = collect(Arr::get($payload, 'users', []));

        $createdProfiles = 0;
        $updatedProfiles = 0;
        $createdUsers = 0;
        $updatedUsers = 0;

        DB::transaction(function () use ($profilesPayload, $usersPayload, &$createdProfiles, &$updatedProfiles, &$createdUsers, &$updatedUsers) {
            $profileIdMap = $this->upsertProfiles($profilesPayload, $createdProfiles, $updatedProfiles);
            $this->upsertUsers($usersPayload, $profileIdMap, $createdUsers, $updatedUsers, $createdProfiles, $updatedProfiles);
        });

        return [
            'created_profiles' => $createdProfiles,
            'updated_profiles' => $updatedProfiles,
            'created_users' => $createdUsers,
            'updated_users' => $updatedUsers,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $profilesPayload
     * @return array<int, int>
     */
    private function upsertProfiles(Collection $profilesPayload, int &$createdProfiles, int &$updatedProfiles): array
    {
        $profileIdMap = [];

        foreach ($profilesPayload as $profileData) {
            if (! is_array($profileData)) {
                continue;
            }

            $externalProfileId = (int) ($profileData['profile_id'] ?? 0);
            $firstName = trim((string) ($profileData['first_name'] ?? ''));
            $middleName = trim((string) ($profileData['middle_name'] ?? ''));
            $lastName = trim((string) ($profileData['last_name'] ?? ''));
            $occupation = trim((string) ($profileData['occupation'] ?? 'Other'));

            if ($firstName === '' || $lastName === '') {
                continue;
            }

            $profile = null;

            if ($externalProfileId > 0) {
                $profile = Profile::query()->find($externalProfileId);
            }

            if (! $profile) {
                $profile = Profile::query()
                    ->where('first_name', $firstName)
                    ->where('middle_name', $middleName)
                    ->where('last_name', $lastName)
                    ->first();
            }

            if ($profile) {
                $profile->update([
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'occupation' => $occupation !== '' ? $occupation : 'Other',
                ]);
                $updatedProfiles++;
            } else {
                $profile = Profile::query()->create([
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'occupation' => $occupation !== '' ? $occupation : 'Other',
                    'address' => null,
                ]);
                $createdProfiles++;
            }

            if ($externalProfileId > 0) {
                $profileIdMap[$externalProfileId] = (int) $profile->getKey();
            }
        }

        return $profileIdMap;
    }

    /**
     * @param  Collection<int, mixed>  $usersPayload
     * @param  array<int, int>  $profileIdMap
     */
    private function upsertUsers(Collection $usersPayload, array $profileIdMap, int &$createdUsers, int &$updatedUsers, int &$createdProfiles, int &$updatedProfiles): void
    {
        foreach ($usersPayload as $userData) {
            if (! is_array($userData)) {
                continue;
            }

            $email = trim((string) ($userData['user_email'] ?? ''));

            if ($email === '') {
                continue;
            }

            $profileId = null;
            $externalProfileId = (int) ($userData['profile_id'] ?? 0);

            if ($externalProfileId > 0 && isset($profileIdMap[$externalProfileId])) {
                $profileId = $profileIdMap[$externalProfileId];
            } elseif (is_array($userData['profile'] ?? null)) {
                $nestedProfile = $this->upsertNestedProfile($userData['profile'], $createdProfiles, $updatedProfiles);
                $profileId = $nestedProfile?->getKey();
            }

            $user = User::query()->where('user_email', $email)->first();

            $payload = [
                'user_password' => (string) ($userData['user_password'] ?? 'password12345'),
                'user_type' => (int) ($userData['user_type'] ?? 3),
                'profile' => $profileId,
                'profile_pending' => false,
            ];

            if ($user) {
                $user->update($payload);
                $updatedUsers++;
            } else {
                User::query()->create(array_merge($payload, [
                    'user_email' => $email,
                ]));
                $createdUsers++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $profileData
     */
    private function upsertNestedProfile(array $profileData, int &$createdProfiles, int &$updatedProfiles): ?Profile
    {
        $firstName = trim((string) ($profileData['first_name'] ?? ''));
        $middleName = trim((string) ($profileData['middle_name'] ?? ''));
        $lastName = trim((string) ($profileData['last_name'] ?? ''));
        $occupation = trim((string) ($profileData['occupation'] ?? 'Other'));

        if ($firstName === '' || $lastName === '') {
            return null;
        }

        $profile = Profile::query()
            ->where('first_name', $firstName)
            ->where('middle_name', $middleName)
            ->where('last_name', $lastName)
            ->first();

        if ($profile) {
            $profile->update([
                'occupation' => $occupation !== '' ? $occupation : 'Other',
            ]);
            $updatedProfiles++;

            return $profile;
        }

        $createdProfiles++;

        return Profile::query()->create([
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'occupation' => $occupation !== '' ? $occupation : 'Other',
            'address' => null,
        ]);
    }
}
