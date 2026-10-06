<?php

namespace App\Forms;

use App\Models\User;
use App\Models\Workplan;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Support\Facades\DB;

/**
 * Registry of dynamic option sources: named, DB-backed lists a `select` or the
 * `search` field can draw its choices from instead of hand-typed options. One
 * admin-choosable "source" key generalises the bespoke special selects
 * (org-select, event-select, …) that hardcode one type per entity.
 *
 * Each source resolves two ways:
 *  - a submitter-facing (scoped) list that respects authorization — an officer
 *    only sees their own organizations' entries — used on the live form and the
 *    submit-time `in:` validation; and
 *  - an admin/unscoped list used by the tally condition editor, which scores
 *    across every organization and must not be limited to the acting admin.
 *
 * Mirrors the code-defined-catalog style of {@see FieldKit} and
 * {@see SystemFunction}. Option `value`s are the stored ids, so the scoring
 * engine's value comparisons work unchanged.
 */
final class OptionSource
{
    public const ORGANIZATIONS = 'organizations';
    public const APPROVED_EVENTS = 'approved_events';
    public const FINALIZED_WORKPLANS = 'finalized_workplans';
    public const OFFICERS = 'officers';

    /**
     * Source key => metadata used by the builder's source picker.
     *
     * `searchable` hints that the list may be large enough to warrant the
     * typeahead search field rather than a plain dropdown.
     *
     * @return array<string, array{label:string, searchable:bool}>
     */
    public static function catalog(): array
    {
        return [
            self::ORGANIZATIONS => ['label' => 'Organizations', 'searchable' => true],
            self::APPROVED_EVENTS => ['label' => 'Approved events', 'searchable' => true],
            self::FINALIZED_WORKPLANS => ['label' => 'Finalized workplans', 'searchable' => false],
            self::OFFICERS => ['label' => 'Officers & members', 'searchable' => true],
        ];
    }

    /**
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::catalog());
    }

    public static function label(string $key): string
    {
        return self::catalog()[$key]['label'] ?? $key;
    }

    public static function isSearchable(string $key): bool
    {
        return (bool) (self::catalog()[$key]['searchable'] ?? false);
    }

    /**
     * The `source` a field is configured to draw options from, or null when the
     * field carries hand-typed options. Unknown source keys are treated as
     * unset (defence against a stale/spoofed value).
     *
     * @param  array<string,mixed>  $options  a field's `field_options`
     */
    public static function forField(array $options): ?string
    {
        $source = (string) ($options['source'] ?? '');

        return $source !== '' && self::has($source) ? $source : null;
    }

    /**
     * Resolve a source into `[['value'=>string,'label'=>string], ...]` option
     * pairs. Scoped resolution restricts the list to the submitter's authorized
     * organizations (officers see only their own); unscoped resolution returns
     * every entry for admin surfaces such as the tally editor.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public static function options(string $key, ?User $user, bool $scoped): array
    {
        if (! self::has($key)) {
            return [];
        }

        $orgIds = $scoped ? self::scopedOrgIds($user) : null;

        return match ($key) {
            self::ORGANIZATIONS => self::organizations($orgIds),
            self::APPROVED_EVENTS => self::approvedEvents($orgIds),
            self::FINALIZED_WORKPLANS => self::finalizedWorkplans($orgIds),
            self::OFFICERS => self::officers($orgIds),
            default => [],
        };
    }

    /**
     * Just the option values for a source, for the submit-time `in:` rule.
     *
     * @return array<int,string>
     */
    public static function values(string $key, ?User $user, bool $scoped): array
    {
        return array_map(
            fn (array $pair) => $pair['value'],
            self::options($key, $user, $scoped),
        );
    }

    /**
     * The organization ids a scoped list is limited to, or null for "all".
     * Officers (user_type 3) are limited to the organizations they hold a role
     * in; everyone else (guests on the public sign-up, admins previewing) sees
     * the full set — mirroring the existing special-select behaviour.
     *
     * @return int[]|null
     */
    private static function scopedOrgIds(?User $user): ?array
    {
        if ($user && (int) $user->user_type === 3) {
            return OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey());
        }

        return null;
    }

    /**
     * @param  int[]|null  $orgIds
     * @return array<int,array{value:string,label:string}>
     */
    private static function organizations(?array $orgIds): array
    {
        $query = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name")])
            ->orderBy('od.name');

        if (is_array($orgIds)) {
            $query->whereIn('o.organization_id', $orgIds ?: [0]);
        }

        return $query->get()
            ->map(fn ($row) => ['value' => (string) $row->organization_id, 'label' => (string) $row->name])
            ->all();
    }

    /**
     * @param  int[]|null  $orgIds
     * @return array<int,array{value:string,label:string}>
     */
    private static function approvedEvents(?array $orgIds): array
    {
        $query = DB::table('event_plans as ep')
            ->where('ep.status', 'approved')
            ->orderByDesc('ep.target_date')
            ->select(['ep.event_plan_id', 'ep.title', 'ep.target_date']);

        if (is_array($orgIds)) {
            $query->whereIn('ep.organization_id', $orgIds ?: [0]);
        }

        return $query->get()
            ->map(fn ($row) => [
                'value' => (string) $row->event_plan_id,
                'label' => trim((string) $row->title.' — '.(string) $row->target_date, ' —'),
            ])
            ->all();
    }

    /**
     * @param  int[]|null  $orgIds
     * @return array<int,array{value:string,label:string}>
     */
    private static function finalizedWorkplans(?array $orgIds): array
    {
        return Workplan::query()
            ->with('semester')
            ->where('status', 'finalized')
            ->when(is_array($orgIds), fn ($q) => $q->whereIn('organization_id', $orgIds ?: [0]))
            ->orderByDesc('workplan_id')
            ->get()
            ->map(fn (Workplan $workplan) => [
                'value' => (string) $workplan->getKey(),
                'label' => (string) ($workplan->semester?->name ?? 'Workplan #'.$workplan->getKey()),
            ])
            ->all();
    }

    /**
     * The people holding an organization role (the merged officers/members
     * roster), each labelled by their full name.
     *
     * @param  int[]|null  $orgIds
     * @return array<int,array{value:string,label:string}>
     */
    private static function officers(?array $orgIds): array
    {
        $query = DB::table('organization_officers as oo')
            ->join('users as u', 'u.user_id', '=', 'oo.user')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereNotNull('oo.user')
            ->select([
                'u.user_id',
                DB::raw("TRIM(CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name)) as full_name"),
                'u.user_email',
            ])
            ->orderBy('full_name');

        if (is_array($orgIds)) {
            $query->whereIn('oo.organization', $orgIds ?: [0]);
        }

        return $query->get()
            ->unique('user_id')
            ->map(fn ($row) => [
                'value' => (string) $row->user_id,
                'label' => trim((string) $row->full_name) !== '' ? (string) $row->full_name : (string) $row->user_email,
            ])
            ->values()
            ->all();
    }
}
