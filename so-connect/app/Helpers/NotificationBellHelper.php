<?php

namespace App\Helpers;

use App\Models\Request as ActionRequest;
use App\Models\User;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationBellHelper
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function buildForUser(User $user, int $hours = 24): Collection
    {
        $hours = max($hours, 1);

        $requestNotifications = self::recentRequestNotifications($user, $hours);
        $eventNotifications = self::upcomingEventNotifications($user, $hours);

        return collect()
            ->merge($requestNotifications->all())
            ->merge($eventNotifications->all())
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function recentRequestNotifications(User $user, int $hours = 24): Collection
    {
        $since = now()->subHours(max($hours, 1));
        $userId = (int) $user->getKey();
        $isSuperAdmin = (int) $user->user_type === 1;

        $officerOrganizationIds = $isSuperAdmin
            ? []
            : OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);

        $presidentOrganizationIds = $isSuperAdmin
            ? []
            : OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);

        $requests = ActionRequest::query()
            ->where('requested_at', '>=', $since)
            ->whereIn('action_type', [1, 2, 7, 8])
            ->orderByDesc('requested_at')
            ->limit(300)
            ->get(['request_id', 'action', 'action_type', 'requested_at', 'user']);

        return $requests
            ->filter(function (ActionRequest $actionRequest) use (
                $userId,
                $isSuperAdmin,
                $officerOrganizationIds,
                $presidentOrganizationIds,
            ) {
                return self::canUserSeeRequest(
                    $actionRequest,
                    $userId,
                    $isSuperAdmin,
                    $officerOrganizationIds,
                    $presidentOrganizationIds,
                );
            })
            ->take(50)
            ->values()
            ->map(function (ActionRequest $actionRequest) use (
                $userId,
                $isSuperAdmin,
                $officerOrganizationIds,
                $presidentOrganizationIds,
            ) {
                $type = self::requestTypeLabel((int) $actionRequest->action_type);
                $isRequester = (int) $actionRequest->user === $userId;

                $canReview = self::canUserReviewRequest(
                    $actionRequest,
                    $officerOrganizationIds,
                    $presidentOrganizationIds,
                    $isSuperAdmin,
                );

                $link = $isSuperAdmin
                    ? route('superadmin.profile-requests')
                    : ($canReview && ! $isRequester ? route('approval-requests') : route('dashboard'));

                $description = 'New '.$type.' submitted.';
                if ($isRequester) {
                    $description = 'You submitted this request.';
                } elseif ($canReview) {
                    $description = 'Requires your review.';
                }

                return [
                    'id' => 'request-'.$actionRequest->request_id,
                    'kind' => 'request',
                    'title' => $type,
                    'description' => $description,
                    'name' => 'Request #'.$actionRequest->request_id,
                    'created_at' => $actionRequest->requested_at,
                    'link' => $link,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function upcomingEventNotifications(User $user, int $hours = 24): Collection
    {
        $now = now();
        $until = (clone $now)->addHours(max($hours, 1));

        $organizationIds = DB::table('members')
            ->where('user', (int) $user->getKey())
            ->pluck('organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values();

        if ($organizationIds->isEmpty()) {
            return collect();
        }

        $query = DB::table('events as e')
            ->join('event_details as ed', 'ed.event_detail_id', '=', 'e.event_detail')
            ->leftJoin('organizations as o', 'o.organization_id', '=', 'e.organization')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('e.organization', $organizationIds->all())
            ->whereBetween('ed.start_time', [$now, $until])
            ->orderBy('ed.start_time')
            ->limit(50)
            ->select([
                'e.event_id',
                'ed.name as event_name',
                'ed.start_time',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
            ]);

        return $query
            ->get()
            ->map(function ($event) {
                return [
                    'id' => 'event-'.$event->event_id,
                    'kind' => 'event',
                    'title' => 'Upcoming Event',
                    'description' => $event->event_name.' · '.$event->organization_name,
                    'name' => $event->event_name,
                    'created_at' => $event->start_time,
                    'link' => route('calendar'),
                ];
            })
            ->values();
    }

    private static function requestTypeLabel(int $actionType): string
    {
        return match ($actionType) {
            1 => 'Membership Request',
            2 => 'Event Request',
            7 => 'Role Change Request',
            8 => 'Profile Match Request',
            default => 'Request',
        };
    }

    /**
     * @param  array<int>  $officerOrganizationIds
     * @param  array<int>  $presidentOrganizationIds
     */
    private static function canUserSeeRequest(
        ActionRequest $actionRequest,
        int $userId,
        bool $isSuperAdmin,
        array $officerOrganizationIds,
        array $presidentOrganizationIds,
    ): bool {
        $actionType = (int) $actionRequest->action_type;

        if ($isSuperAdmin) {
            return $actionType === 8;
        }

        if ($actionType === 8) {
            return false;
        }

        if ((int) $actionRequest->user === $userId) {
            return true;
        }

        return self::canUserReviewRequest(
            $actionRequest,
            $officerOrganizationIds,
            $presidentOrganizationIds,
            $isSuperAdmin,
        );
    }

    /**
     * @param  array<int>  $officerOrganizationIds
     * @param  array<int>  $presidentOrganizationIds
     */
    private static function canUserReviewRequest(
        ActionRequest $actionRequest,
        array $officerOrganizationIds,
        array $presidentOrganizationIds,
        bool $isSuperAdmin,
    ): bool {
        $actionType = (int) $actionRequest->action_type;

        if ($isSuperAdmin) {
            return $actionType === 8;
        }

        if (in_array($actionType, [1, 2], true)) {
            $organizationId = self::parseLeadingOrganizationId($actionRequest->action);

            if ($organizationId <= 0) {
                return false;
            }

            if ($actionType === 1) {
                return in_array($organizationId, $officerOrganizationIds, true);
            }

            return in_array($organizationId, $presidentOrganizationIds, true);
        }

        if ($actionType === 7) {
            [, $organizationId] = self::parseRoleChangeAction($actionRequest->action);

            return $organizationId > 0 && in_array($organizationId, $presidentOrganizationIds, true);
        }

        return false;
    }

    private static function parseLeadingOrganizationId(?string $action): int
    {
        $parts = array_map('trim', explode('|', (string) $action));
        $segment = $parts[0] ?? '';

        return ctype_digit($segment) ? (int) $segment : 0;
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function parseRoleChangeAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        $userId = ctype_digit($parts[0] ?? '') ? (int) $parts[0] : 0;
        $organizationId = ctype_digit($parts[1] ?? '') ? (int) $parts[1] : 0;

        return [$userId, $organizationId];
    }
}
