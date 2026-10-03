<?php

namespace App\Forms;

use App\Models\Form;
use App\Models\User;
use App\Models\Workplan;
use App\Services\OrganizationAuthorizationService;
use App\Services\WorkplanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Server-resolved option data for the special (kit-scoped) field types: the
 * organization picker, event picker (approved event plans), workplan picker
 * and the workplan approved-events multi-select. Shared by the live renderer
 * and the admin preview so both draw the same lists.
 */
final class SpecialFieldData
{
    /**
     * @param  Collection<int,\App\Models\Form\FormDescription>  $fields
     * @return array{organizations:array, events:array, workplans:array, approved_plans:array, scanner:mixed, sources:array}
     */
    public static function resolve(?User $user, Collection $fields, Form $form): array
    {
        $types = $fields->pluck('field_type')->all();
        $hasTableEventColumn = $fields->contains(function ($field) {
            return $field->field_type === FieldType::TABLE_INPUT
                && collect(FieldType::tableColumns((array) ($field->field_options ?? [])))
                    ->contains(fn ($col) => $col['type'] === 'event-select');
        });

        $data = [
            'organizations' => [],
            'events' => [],
            'workplans' => [],
            'approved_plans' => [],
            'scanner' => null,
            // fieldKey => ['options' => [{value,label}], 'searchable' => bool]
            // for `select`/`search` fields drawing from a dynamic OptionSource,
            // resolved scoped to the submitter.
            'sources' => self::optionSources($user, $fields),
        ];

        if (in_array(FieldType::ID_SCAN, $types, true)) {
            $data['scanner'] = [
                'orientation' => \App\Models\IdTemplate::scannerTemplate()?->orientation ?: 'vertical',
                'templates' => \App\Models\IdTemplate::scannerChoices(),
            ];
        }

        $officerOrgIds = ($user && (int) $user->user_type === 3)
            ? OrganizationAuthorizationService::officerOrganizationIdsForUser((int) $user->getKey())
            : [];

        if (in_array(FieldType::ORG_SELECT, $types, true)) {
            $data['organizations'] = self::organizations($user, $officerOrgIds, $form);
        }

        if (in_array(FieldType::EVENT_SELECT, $types, true) || $hasTableEventColumn) {
            $dedupe = $fields->first(fn ($f) => $f->field_type === FieldType::EVENT_SELECT
                && (bool) (($f->field_options['dedupe'] ?? false))) !== null;
            $data['events'] = self::hasCurrentSemesterEventColumn($fields)
                ? self::currentWorkplanPlans($officerOrgIds)
                : self::approvedEventPlans($officerOrgIds, $dedupe ? $form : null);
        }

        if (in_array(FieldType::WORKPLAN_SELECT, $types, true)) {
            $data['workplans'] = self::finalizedWorkplans($officerOrgIds);
        }

        if (in_array(FieldType::WORKPLAN_EVENTS, $types, true)) {
            $data['approved_plans'] = self::currentWorkplanPlans($officerOrgIds);
        }

        return $data;
    }

    /**
     * Resolve every `select`/`search` field that draws from a dynamic
     * OptionSource into its scoped option list, keyed by field key. Scoped so a
     * submitter is only ever offered entries they are authorized to see.
     *
     * @param  Collection<int,\App\Models\Form\FormDescription>  $fields
     * @return array<string,array{options:array<int,array{value:string,label:string}>,searchable:bool}>
     */
    private static function optionSources(?User $user, Collection $fields): array
    {
        $sources = [];

        foreach ($fields as $field) {
            if (! in_array($field->field_type, [FieldType::SELECT, FieldType::SEARCH], true)) {
                continue;
            }
            $source = OptionSource::forField((array) ($field->field_options ?? []));
            if ($source === null) {
                continue;
            }
            $sources[$field->field_key] = [
                'options' => OptionSource::options($source, $user, scoped: true),
                'searchable' => OptionSource::isSearchable($source),
            ];
        }

        return $sources;
    }

    /**
     * Organizations the submitter may pick: their officer orgs for regular
     * users, every org for guests (public sign-up), admins (preview), and
     * Membership Registration — that form's whole point is picking a
     * DIFFERENT org than the submitter's own.
     *
     * @param  int[]  $officerOrgIds
     * @return array<int,array{id:int,name:string}>
     */
    private static function organizations(?User $user, array $officerOrgIds, ?Form $form = null): array
    {
        $query = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->select(['o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name")])
            ->orderBy('od.name');

        $isMembershipRegistration = $form?->system_function === SystemFunction::MEMBERSHIP_REGISTRATION;

        if ($user && (int) $user->user_type === 3 && ! $isMembershipRegistration) {
            $query->whereIn('o.organization_id', $officerOrgIds);
        }

        return $query->get()
            ->map(fn ($row) => ['id' => (int) $row->organization_id, 'name' => (string) $row->name])
            ->all();
    }

    /**
     * The org's approved event plans (the legacy "event" source), each with
     * the autofill metadata the event picker offers, optionally excluding
     * plans already reported through this form.
     *
     * @param  int[]  $orgIds
     * @return array<int,array<string,mixed>>
     */
    private static function approvedEventPlans(array $orgIds, ?Form $dedupeForm): array
    {
        if ($orgIds === []) {
            return [];
        }

        $usedIds = $dedupeForm
            ? DB::table('form_submissions')
                ->where('form_id', (int) $dedupeForm->getKey())
                ->whereNotNull('event_id')
                ->pluck('event_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $raw = DB::table('event_plans as ep')
            ->whereIn('ep.organization_id', $orgIds)
            ->where('ep.status', 'approved')
            ->when($usedIds !== [], fn ($q) => $q->whereNotIn('ep.event_plan_id', $usedIds))
            ->orderByDesc('ep.target_date')
            ->select(['ep.event_plan_id', 'ep.organization_id', 'ep.title', 'ep.target_date', 'ep.activity_types', 'ep.persons_responsible'])
            ->get();

        $personNames = self::personNames(
            $raw->flatMap(fn ($ev) => self::decodeIds($ev->persons_responsible))->unique()->values()->all(),
        );

        return $raw->map(fn ($ev) => [
            'id' => (int) $ev->event_plan_id,
            'organization_id' => (int) $ev->organization_id,
            'title' => (string) $ev->title,
            'date' => (string) $ev->target_date,
            'activity_types' => is_array($decoded = json_decode((string) $ev->activity_types, true)) ? $decoded : [],
            'people' => collect(self::decodeIds($ev->persons_responsible))
                ->map(fn ($id) => $personNames[$id] ?? null)
                ->filter()
                ->implode(', '),
        ])->all();
    }

    /**
     * Finalized workplans of the submitter's orgs, each carrying its approved
     * activities for the read-only preview under the picker.
     *
     * @param  int[]  $orgIds
     * @return array<int,array<string,mixed>>
     */
    private static function finalizedWorkplans(array $orgIds): array
    {
        if ($orgIds === []) {
            return [];
        }

        $service = app(WorkplanService::class);

        return Workplan::query()
            ->with('semester')
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'finalized')
            ->orderByDesc('workplan_id')
            ->get()
            ->map(function (Workplan $workplan) use ($service) {
                $plans = $service->getApprovedPlansForWorkplan($workplan);

                return [
                    'id' => (int) $workplan->getKey(),
                    'label' => trim(($workplan->semester?->name ?? 'Workplan').' — '.$plans->count().' activities'),
                    'activities' => $plans->map(fn ($plan) => [
                        'title' => (string) $plan->title,
                        'date' => (string) $plan->target_date,
                        'resources' => (string) ($plan->resources_needed ?? ''),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Whether any table-input field carries an `event-select` column scoped to
     * `current_semester` — those draw their options from the current-semester
     * approved workplan activities instead of the org's whole approved-plan
     * history. Read directly off the raw column config (not
     * {@see FieldType::tableColumns()}, which strips the `scope` property).
     *
     * @param  Collection<int,\App\Models\Form\FormDescription>  $fields
     */
    private static function hasCurrentSemesterEventColumn(Collection $fields): bool
    {
        return $fields->contains(function ($field) {
            if ($field->field_type !== FieldType::TABLE_INPUT) {
                return false;
            }

            foreach ((array) (($field->field_options ?? [])['columns'] ?? []) as $column) {
                if (is_array($column) && ($column['type'] ?? null) === 'event-select' && ($column['scope'] ?? null) === 'current_semester') {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Approved plans of the org's current (active-semester) workplan — the
     * option list for the workplan-events multi-select, all checked by
     * default at render.
     *
     * @param  int[]  $orgIds
     * @return array<int,array<string,mixed>>
     */
    private static function currentWorkplanPlans(array $orgIds): array
    {
        if ($orgIds === []) {
            return [];
        }

        $service = app(WorkplanService::class);
        $semester = $service->getActiveSemester();
        if ($semester === null) {
            return [];
        }

        $plans = [];
        foreach ($orgIds as $orgId) {
            $workplan = Workplan::query()
                ->where('organization_id', (int) $orgId)
                ->where('semester_id', (int) $semester->getKey())
                ->first();
            if ($workplan === null) {
                continue;
            }
            foreach ($service->getApprovedPlansForWorkplan($workplan) as $plan) {
                $plans[] = [
                    'id' => (int) $plan->getKey(),
                    'title' => (string) $plan->title,
                    'date' => (string) $plan->target_date,
                    'workplan_id' => (int) $workplan->getKey(),
                ];
            }
        }

        return $plans;
    }

    /**
     * @return array<int,int>
     */
    private static function decodeIds(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);

        return is_array($decoded) ? array_values(array_filter(array_map('intval', $decoded))) : [];
    }

    /**
     * @param  int[]  $userIds
     * @return array<int,string>
     */
    private static function personNames(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('u.user_id', $userIds)
            ->select(['u.user_id', DB::raw("TRIM(CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name)) as full_name")])
            ->get()
            ->pluck('full_name', 'user_id')
            ->map(fn ($name) => (string) $name)
            ->all();
    }
}
