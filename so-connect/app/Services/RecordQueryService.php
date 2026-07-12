<?php

namespace App\Services;

use App\Helpers\OrganizationLogoHelper;
use App\Models\ActionLog;
use App\Models\Approval;
use App\Models\LoginLog;
use App\Models\Organization;
use App\Models\Request as ActionRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RecordQueryService
{
    /**
     * @param  array{from?: string, to?: string, org_id?: string, name?: string}  $filters
     */
    public function loginLogsQuery(array $filters = []): Builder
    {
        $query = LoginLog::with(['user.profile'])->orderByDesc('logged_at');

        if (! empty($filters['from'])) {
            $query->where('logged_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('logged_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        if (! empty($filters['org_id'])) {
            $orgId = $filters['org_id'];
            $query->whereIn('user_id', function ($subQuery) use ($orgId) {
                $subQuery->select('user')
                    ->from('organization_officers')
                    ->where('organization', $orgId);
            });
        }

        if (! empty($filters['name'])) {
            $name = $filters['name'];
            $query->whereHas('user', function ($userQuery) use ($name) {
                $userQuery->where('user_email', 'like', "%{$name}%")
                    ->orWhereHas('profile', function ($profileQuery) use ($name) {
                        $profileQuery->where('first_name', 'like', "%{$name}%")
                            ->orWhere('middle_name', 'like', "%{$name}%")
                            ->orWhere('last_name', 'like', "%{$name}%");
                    });
            });
        }

        return $query;
    }

    public function mapLoginLog(LoginLog $log): array
    {
        $user = $log->user;
        // `profile` is a FK column on users, so `$user->profile` returns the id and
        // shadows the relation; read the eager-loaded relation directly instead.
        $profile = $user?->getRelations()['profile'] ?? null;
        $nameParts = array_filter([
            $profile?->first_name,
            $profile?->middle_name,
            $profile?->last_name,
        ], fn ($part) => trim((string) $part) !== '');
        $name = $nameParts ? trim(implode(' ', $nameParts)) : '';
        if ($name === '') {
            $name = $user?->user_email ?? '';
        }

        return [
            'user_email' => $user?->user_email ?? '-',
            'name' => $name !== '' ? $name : '-',
            'interaction' => $log->interaction,
            'logged_at' => optional($log->logged_at)->toDateTimeString(),
        ];
    }

    /**
     * @param  array{from?: string, to?: string, org_id?: string, name?: string}  $filters
     */
    public function getLoginLogs(array $filters = []): array
    {
        return $this->loginLogsQuery($filters)
            ->get()
            ->map(fn (LoginLog $log) => $this->mapLoginLog($log))
            ->all();
    }

    /**
     * Administrator action logs, filterable by user (name/email), category
     * and date range — the same shape as loginLogsQuery().
     *
     * @param  array{from?: string, to?: string, category?: string, user?: string}  $filters
     */
    public function actionLogsQuery(array $filters = []): Builder
    {
        $query = ActionLog::with(['user.profile'])->orderByDesc('created_at');

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['user'])) {
            $user = $filters['user'];
            $query->whereHas('user', function ($userQuery) use ($user) {
                $userQuery->where('user_email', 'like', "%{$user}%")
                    ->orWhereHas('profile', function ($profileQuery) use ($user) {
                        $profileQuery->where('first_name', 'like', "%{$user}%")
                            ->orWhere('middle_name', 'like', "%{$user}%")
                            ->orWhere('last_name', 'like', "%{$user}%");
                    });
            });
        }

        return $query;
    }

    public function mapActionLog(ActionLog $log): array
    {
        $user = $log->user;
        // `profile` is a FK column on users and shadows the relation (see
        // mapLoginLog above).
        $profile = $user?->getRelations()['profile'] ?? null;
        $nameParts = array_filter([
            $profile?->first_name,
            $profile?->middle_name,
            $profile?->last_name,
        ], fn ($part) => trim((string) $part) !== '');
        $name = $nameParts ? trim(implode(' ', $nameParts)) : ($user?->user_email ?? '');

        return [
            'user_email' => $user?->user_email ?? '-',
            'name' => $name !== '' ? $name : '-',
            'category' => $log->category,
            'category_label' => ActionLogger::categoryLabel($log->category),
            'action' => $log->action,
            'description' => $log->description ?? '',
            'meta' => (array) ($log->meta ?? []),
            'created_at' => optional($log->created_at)->toDateTimeString(),
        ];
    }

    /**
     * @param  array{from?: string, to?: string, category?: string, user?: string}  $filters
     */
    public function getActionLogs(array $filters = []): array
    {
        return $this->actionLogsQuery($filters)
            ->get()
            ->map(fn (ActionLog $log) => $this->mapActionLog($log))
            ->all();
    }

    public function getRequestRecords(?string $orgId, bool $excludeAdminUsers = false): array
    {
        $query = ActionRequest::with(['requestType', 'requester'])->orderByDesc('requested_at');

        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        if ($excludeAdminUsers) {
            $query->whereHas('requester', fn ($q) => $q->whereNotIn('user_type', [1, 2]));
        }

        $allRequests = $query->get();

        $approvedIds = Approval::where('is_rejected', false)->pluck('request')->flip();
        $rejectedIds = Approval::where('is_rejected', true)->pluck('request')->flip();

        $accepted = [];
        $pending = [];
        $rejected = [];

        foreach ($allRequests as $req) {
            $row = [
                'user_id' => $req->user !== null ? $req->user : 'Guest',
                'org_id' => $req->organization_id,
                'request_time' => optional($req->requested_at)->toDateTimeString(),
                'request_type' => optional($req->requestType)->name ?? 'Unknown',
            ];

            if (isset($rejectedIds[$req->request_id])) {
                $rejected[] = $row;
            } elseif (isset($approvedIds[$req->request_id])) {
                $accepted[] = $row;
            } else {
                $pending[] = $row;
            }
        }

        return compact('accepted', 'pending', 'rejected');
    }

    /**
     * Organizations for the Database View, with logo + officer counts.
     */
    public function getOrganizations(): array
    {
        $logoMap = OrganizationLogoHelper::map();

        $counts = DB::table('organization_officers')
            ->whereIn('role', ['officer', 'president'])
            ->select('organization', DB::raw('COUNT(*) as officer_count'))
            ->groupBy('organization')
            ->pluck('officer_count', 'organization');

        return Organization::with('detail')
            ->orderBy('organization_id')
            ->get()
            ->map(function (Organization $org) use ($logoMap, $counts) {
                $name = $org->getRelation('detail')?->name ?? 'Unnamed Organization';

                return [
                    'organization_id' => $org->organization_id,
                    'name' => $name,
                    'initials' => $org->getRelation('detail')?->initials,
                    'logo_url' => $logoMap[$name] ?? null,
                    'officer_count' => (int) ($counts[$org->organization_id] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * President + Officer roster grouped by organization id.
     */
    public function getOrganizationOfficers(): array
    {
        return DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('oo.role', ['officer', 'president'])
            ->orderBy('oo.organization')
            ->orderByDesc('oo.role')
            ->select([
                'oo.organization',
                'u.user_email',
                'oo.role',
                'oo.position',
                'oo.member_since',
                DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.middle_name,''), ' ', COALESCE(p.last_name,''))) as name"),
            ])
            ->get()
            ->map(fn ($o) => [
                'organization' => $o->organization,
                'name' => trim(preg_replace('/\s+/', ' ', (string) $o->name)) ?: ($o->user_email ?? '—'),
                'email' => $o->user_email ?? '—',
                'role' => ucfirst((string) $o->role),
                'position' => $o->position ?: '—',
                'member_since' => $o->member_since ? Carbon::parse($o->member_since)->toDateString() : '—',
            ])
            ->groupBy('organization')
            ->map(fn ($rows) => $rows->values()->all())
            ->all();
    }
}
