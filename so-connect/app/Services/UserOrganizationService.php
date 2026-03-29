<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Support\Collection;

class UserOrganizationService
{
    public function getUserOrganizations(User $user): Collection
    {
        $userId = (int) $user->getKey();

        $approvedMemberships = $user
            ->member()
            ->with('organizationrelation.organizationDetail')
            ->get();

        $approvedOrganizationIds = $approvedMemberships
            ->pluck('organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->all();

        $approvedEntries = $approvedMemberships
            ->map(function ($member) {
                return (object) [
                    'organization_id' => (int) $member->organization,
                    'organization_name' => $member->organizationrelation?->organizationDetail?->organization_name ?? 'Unknown Organization',
                    'role_name' => $member->role ?? 'Member',
                    'member_since' => $member->member_since,
                    'status' => $member->approval_id ? 'Approved' : 'Pending',
                ];
            });

        $pendingRequests = RequestModel::where('action_type', 0)
            ->doesntHave('approval')
            ->get()
            ->map(function ($request) {
                $parts = explode('|', (string) $request->action);

                if (count($parts) < 2) {
                    return null;
                }

                return [
                    'organization_id' => (int) $parts[0],
                    'user_id' => (int) $parts[1],
                    'requested_at' => $request->request_made_at,
                ];
            })
            ->filter()
            ->filter(function ($request) use ($userId, $approvedOrganizationIds) {
                return $request['user_id'] === $userId
                    && ! in_array($request['organization_id'], $approvedOrganizationIds, true);
            })
            ->sortByDesc('requested_at')
            ->unique('organization_id')
            ->values();

        $pendingOrganizations = Organization::with('organizationDetail')
            ->whereIn('organization_id', $pendingRequests->pluck('organization_id')->values())
            ->get()
            ->keyBy('organization_id');

        $pendingEntries = $pendingRequests->map(function ($request) use ($pendingOrganizations) {
            $organization = $pendingOrganizations->get($request['organization_id']);

            return (object) [
                'organization_id' => $request['organization_id'],
                'organization_name' => $organization?->organizationDetail?->organization_name ?? 'Unknown Organization',
                'role_name' => 'Member',
                'member_since' => null,
                'status' => 'Pending',
            ];
        });

        return $approvedEntries
            ->concat($pendingEntries)
            ->values();
    }
}
