<?php

namespace App\Helpers;

use App\Models\Profile;
use Illuminate\Support\Collection;

class ProfileMatchHelper
{
    public static function findClosestMatch(string $firstName, string $lastName, ?string $middleName = null): ?Profile
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $middleName = trim((string) $middleName);

        if ($firstName === '' && $lastName === '') {
            return null;
        }

        $candidates = Profile::query()
            ->when($lastName !== '', function ($query) use ($lastName) {
                $query->where(function ($innerQuery) use ($lastName) {
                    $innerQuery->where('last_name', 'like', $lastName.'%')
                        ->orWhere('last_name', 'like', '%'.$lastName.'%');
                });
            })
            ->when($firstName !== '', function ($query) use ($firstName) {
                $query->orWhere('first_name', 'like', $firstName.'%');
            })
            ->limit(250)
            ->get(['profile_id', 'first_name', 'middle_name', 'last_name', 'occupation']);

        if ($candidates->isEmpty()) {
            $candidates = Profile::query()->limit(250)->get(['profile_id', 'first_name', 'middle_name', 'last_name', 'occupation']);
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        $needle = self::normalizedFullName($firstName, $middleName, $lastName);

        return $candidates
            ->map(function (Profile $profile) use ($needle, $firstName, $lastName) {
                $candidateName = self::normalizedFullName(
                    (string) $profile->first_name,
                    (string) $profile->middle_name,
                    (string) $profile->last_name,
                );

                similar_text($needle, $candidateName, $score);

                $boost = 0;
                if ($lastName !== '' && strcasecmp($lastName, (string) $profile->last_name) === 0) {
                    $boost += 12;
                }
                if ($firstName !== '' && strcasecmp($firstName, (string) $profile->first_name) === 0) {
                    $boost += 8;
                }

                return [
                    'profile' => $profile,
                    'score' => $score + $boost,
                ];
            })
            ->sortByDesc('score')
            ->first()['profile'] ?? null;
    }

    /**
     * @return Collection<int, Profile>
     */
    public static function search(string $query, int $limit = 20, bool $adminsOnly = false): Collection
    {
        $query = trim($query);

        $columns = ['profile_id', 'first_name', 'middle_name', 'last_name', 'occupation', 'course_year', 'sex'];

        // Restrict to profiles linked to an administrator account (user_type 1/2)
        // — used by the Action Logs filter, whose entries are all admin actions.
        $adminScope = function ($builder) use ($adminsOnly) {
            if ($adminsOnly) {
                $builder->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('users')
                        ->whereColumn('users.profile', 'profiles.profile_id')
                        ->whereIn('users.user_type', [1, 2]);
                });
            }
        };

        if ($query === '') {
            return Profile::query()
                ->where($adminScope)
                ->orderByDesc('profile_id')
                ->limit($limit)
                ->get($columns);
        }

        return Profile::query()
            ->where(function ($builder) use ($query) {
                $builder->where('first_name', 'like', '%'.$query.'%')
                    ->orWhere('middle_name', 'like', '%'.$query.'%')
                    ->orWhere('last_name', 'like', '%'.$query.'%')
                    ->orWhere('occupation', 'like', '%'.$query.'%');
            })
            ->where($adminScope)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get($columns);
    }

    private static function normalizedFullName(string $firstName, string $middleName, string $lastName): string
    {
        return strtolower(trim(implode(' ', array_filter([$firstName, $middleName, $lastName]))));
    }
}
