<?php

namespace App\Helpers;

use App\Models\Request as ActionRequest;
use App\Models\Semester;
use App\Models\User;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Support\Carbon;
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
        $semesterWarnings = self::semesterWarningNotifications($user);
        $documentNotifications = self::newDocumentNotifications($user, $hours);

        return collect()
            ->merge($semesterWarnings->all())
            ->merge($requestNotifications->all())
            ->merge($eventNotifications->all())
            ->merge($documentNotifications->all())
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * @return array<int, int>
     */
    public static function organizationAlertCountsForUser(User $user): array
    {
        $counts = [];

        foreach (self::buildForUser($user, 24) as $notification) {
            $organizationId = (int) (($notification['organization_id'] ?? 0));
            if ($organizationId <= 0) {
                continue;
            }

            $counts[$organizationId] = ($counts[$organizationId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function semesterWarningNotifications(User $user): Collection
    {
        if ((int) $user->user_type !== 2) {
            return collect();
        }

        $activeSemester = Semester::currentlyActive();
        if (! $activeSemester) {
            return collect();
        }

        $endDate = $activeSemester->activePeriodEnd();
        $today = Carbon::today();
        $daysLeft = (int) $today->diffInDays($endDate, false);

        if ($daysLeft < 0 || $daysLeft > 30) {
            return collect();
        }

        $label = $daysLeft === 0 ? 'today' : ($daysLeft === 1 ? 'tomorrow' : "in {$daysLeft} days");

        return collect([[
            'id' => 'semester-warning-'.$activeSemester->semester_id,
            'kind' => 'system',
            'title' => 'Preparation Period Ending Soon',
            'description' => "The preparation period for \"{$activeSemester->name}\" ends {$label}. Assign the next semester now.",
            'name' => 'Semester Alert',
            'created_at' => now()->toDateTimeString(),
            'link' => route('admin.semesters.index'),
        ]]);
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
            ->whereIn('action_type', [1, 2, 3, 4, 7, 8, 9])
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
                    : ($canReview && ! $isRequester && (int) $actionRequest->action_type === 1
                        ? route('membership-requests')
                        : route('dashboard'));

                $description = 'New '.$type.' submitted.';
                if ($isRequester) {
                    $description = 'You submitted this request.';
                } elseif ($canReview) {
                    $description = 'Requires your review.';
                }

                $organizationId = self::parseLeadingOrganizationId($actionRequest->action);

                return [
                    'id' => 'request-'.$actionRequest->request_id,
                    'kind' => 'request',
                    'title' => $type,
                    'description' => $description,
                    'name' => 'Request #'.$actionRequest->request_id,
                    'created_at' => $actionRequest->requested_at,
                    'organization_id' => $organizationId,
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

        $organizationIds = collect(OrganizationAuthorizationService::memberOrganizationIdsForUser((int) $user->getKey()));

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
                'e.organization as organization_id',
                'ed.name as event_name',
                'ed.start_time',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
            ]);

        return $query
            ->get()
            ->map(function ($event) {
                $organizationId = (int) ($event->organization_id ?? 0);

                return [
                    'id' => 'event-'.$event->event_id,
                    'kind' => 'event',
                    'title' => 'Upcoming Event',
                    'description' => $event->event_name.' · '.$event->organization_name,
                    'name' => $event->event_name,
                    'created_at' => $event->start_time,
                    'organization_id' => $organizationId,
                    'link' => route('calendar'),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function newDocumentNotifications(User $user, int $hours): Collection
    {
        $since = now()->subHours(max($hours, 1));
        $userId = (int) $user->getKey();
        $isAdmin = in_array((int) $user->user_type, [1, 2], true);

        $query = DB::table('generated_documents as gd')
            ->join('form_submissions as fs', 'fs.form_submission_id', '=', 'gd.form_submission_id')
            ->leftJoin('forms as f', 'f.id', '=', 'fs.form_id')
            ->where('gd.status', 'generated')
            ->where('gd.generated_at', '>=', $since)
            ->select(['gd.generated_document_id', 'gd.generated_at', 'f.name as form_name', 'fs.organization_id']);

        if (! $isAdmin) {
            $orgIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
            if (empty($orgIds)) {
                return collect();
            }
            $query->whereIn('fs.organization_id', $orgIds);
        }

        return $query->get()->map(fn ($row) => [
            'id'          => 'doc-'.$row->generated_document_id,
            'kind'        => 'document',
            'title'       => 'Document Available',
            'description' => ($row->form_name ?? 'A document').' is ready for download.',
            'name'        => $row->form_name ?? 'Document',
            'created_at'  => $row->generated_at,
            'organization_id' => (int) ($row->organization_id ?? 0),
            'link'        => route('documents.index'),
        ])->values();
    }

    private static function requestTypeLabel(int $actionType): string
    {
        return match ($actionType) {
            1 => 'Membership Request',
            2 => 'Event Request',
            3 => 'Document Generation Request',
            4 => 'Document Access Request',
            7 => 'Role Change Request',
            8 => 'Form Upload Request',
            9 => 'Profile Match Request',
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
            return $actionType === 9;
        }

        if ($actionType === 9) {
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
            return $actionType === 9;
        }

        if (in_array($actionType, [1, 2, FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION, FormTemplateHelper::ACTION_TYPE_DOCUMENT_ACCESS, FormTemplateHelper::ACTION_TYPE_FORM_UPLOAD], true)) {
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
