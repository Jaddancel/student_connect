<?php

namespace App\Forms;

use App\Helpers\FormTemplateHelper;
use App\Models\EventPlan;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\Workplan;
use App\Services\WorkplanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves an Activity Table field into its printable snapshot: one row per
 * approved activity of the submitter's organization(s), each column a
 * pre-formatted string. The same event set the workplan's approved-events
 * multi-select uses (current-semester approved parent plans), so the printed
 * table and the checklist agree.
 *
 * This is computed at submit time and stored on the payload — what was true at
 * submission is what prints — so it takes the submitter's officer org ids and
 * returns strings rather than live models.
 *
 * @see SpecialFieldData::currentWorkplanPlans() the sibling that lists the same
 *      plans for the web checklist.
 */
final class ActivityTableData
{
    /**
     * Column keys resolved directly from the plan models rather than from the
     * originating New Events submission payload.
     */
    private const CANONICAL_KEYS = [
        'title', 'purpose_of_activity', 'resources_needed', 'target_date',
        'event_location', 'event_start_time', 'event_end_time',
        'organization_id', 'persons_responsible',
    ];

    /**
     * @param  array<string,mixed>  $fieldOptions  the activity-table field's options
     * @param  int[]  $officerOrgIds
     * @return array{columns: list<array{key:string,label:string,type:string}>, rows: list<array<string,string>>}
     */
    public static function forField(array $fieldOptions, array $officerOrgIds): array
    {
        $columns = FieldType::activityTableColumns($fieldOptions);

        $parents = self::approvedParentPlans($officerOrgIds);
        if ($parents->isEmpty() || $columns === []) {
            return ['columns' => $columns, 'rows' => []];
        }

        $colKeys = array_column($columns, 'key');
        $parentIds = $parents->pluck('event_plan_id')->map(fn ($id) => (int) $id)->all();

        // Batch every lookup a column might need (no N+1 across the plan set):
        // child plans (schedule/location) → their generation requests →
        // originating submissions (custom New Events fields), plus org/person
        // name maps and the New Events field metadata for custom columns.
        $children = self::childPlansByParent($parentIds);
        $submissionByParent = self::submissionPayloadByParent($children);
        $eventFieldsByKey = self::eventFieldsByKey();
        $orgNames = in_array('organization_id', $colKeys, true)
            ? self::orgNames($parents->pluck('organization_id')->map(fn ($id) => (int) $id)->unique()->all())
            : [];
        $personNames = in_array('persons_responsible', $colKeys, true)
            ? self::personNames($parents->flatMap(fn (EventPlan $p) => (array) ($p->persons_responsible ?? []))
                ->map(fn ($id) => (int) $id)->unique()->filter()->all())
            : [];

        $rows = $parents->map(function (EventPlan $parent) use ($columns, $children, $submissionByParent, $eventFieldsByKey, $orgNames, $personNames) {
            $child = $children->get((int) $parent->getKey());
            $payload = $submissionByParent[(int) $parent->getKey()] ?? [];

            $row = [];
            foreach ($columns as $column) {
                $row[$column['key']] = self::cellValue($column, $parent, $child, $payload, $eventFieldsByKey, $orgNames, $personNames);
            }

            return $row;
        })->values()->all();

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * The current-semester approved parent plans across the submitter's orgs —
     * the same set {@see SpecialFieldData::currentWorkplanPlans()} lists.
     *
     * @param  int[]  $orgIds
     * @return Collection<int,EventPlan>
     */
    private static function approvedParentPlans(array $orgIds): Collection
    {
        if ($orgIds === []) {
            return collect();
        }

        $service = app(WorkplanService::class);
        $semester = $service->getActiveSemester();
        if ($semester === null) {
            return collect();
        }

        $plans = collect();
        foreach ($orgIds as $orgId) {
            $workplan = Workplan::query()
                ->where('organization_id', (int) $orgId)
                ->where('semester_id', (int) $semester->getKey())
                ->first();
            if ($workplan === null) {
                continue;
            }
            $plans = $plans->concat($service->getApprovedPlansForWorkplan($workplan));
        }

        return $plans->values();
    }

    /**
     * @param  int[]  $parentIds
     * @return Collection<int,EventPlan>  keyed by parent_plan_id
     */
    private static function childPlansByParent(array $parentIds): Collection
    {
        if ($parentIds === []) {
            return collect();
        }

        return EventPlan::query()
            ->whereIn('parent_plan_id', $parentIds)
            ->orderBy('event_plan_id')
            ->get()
            ->keyBy('parent_plan_id');
    }

    /**
     * Map each parent plan to its originating New Events submission payload,
     * via the child plan's generation request. Legacy plans without a child /
     * request / submission simply don't appear (custom cells fall back to '').
     *
     * @param  Collection<int,EventPlan>  $children  keyed by parent_plan_id
     * @return array<int,array<string,mixed>>
     */
    private static function submissionPayloadByParent(Collection $children): array
    {
        $requestIds = $children->pluck('request_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($requestIds === []) {
            return [];
        }

        $requests = ActionRequest::query()->whereIn('request_id', $requestIds)->get()->keyBy('request_id');

        $submissionIdByParent = [];
        foreach ($children as $parentId => $child) {
            $request = $child->request_id ? $requests->get((int) $child->request_id) : null;
            if ($request === null) {
                continue;
            }
            $submissionId = (int) (((array) ($request->payload ?? []))['submission_id'] ?? 0);
            if ($submissionId <= 0) {
                $submissionId = FormTemplateHelper::decodeDocumentGenerationAction($request->action)[1];
            }
            if ($submissionId > 0) {
                $submissionIdByParent[(int) $parentId] = $submissionId;
            }
        }

        if ($submissionIdByParent === []) {
            return [];
        }

        $submissions = FormSubmission::query()
            ->whereIn('form_submission_id', array_values(array_unique($submissionIdByParent)))
            ->get()
            ->keyBy('form_submission_id');

        $payloads = [];
        foreach ($submissionIdByParent as $parentId => $submissionId) {
            $payloads[$parentId] = (array) ($submissions->get($submissionId)?->payload ?? []);
        }

        return $payloads;
    }

    /**
     * New Events form field metadata, keyed by field key, for resolving custom
     * columns off the originating submission with the field's declared type.
     *
     * @return array<string,\App\Models\Form\FormDescription>
     */
    private static function eventFieldsByKey(): array
    {
        $form = SystemFunction::form(SystemFunction::NEW_EVENT);
        if ($form === null) {
            return [];
        }

        return $form->fields()->get()->keyBy('field_key')->all();
    }

    /**
     * One pre-formatted string cell. Canonical columns read the plan models
     * (parent for the tally fields, child for the schedule/location); everything
     * else resolves off the originating New Events submission payload.
     *
     * @param  array{key:string,label:string,type:string}  $column
     * @param  array<string,mixed>  $submissionPayload
     * @param  array<string,\App\Models\Form\FormDescription>  $eventFieldsByKey
     * @param  array<int,string>  $orgNames
     * @param  array<int,string>  $personNames
     */
    private static function cellValue(
        array $column,
        EventPlan $parent,
        ?EventPlan $child,
        array $submissionPayload,
        array $eventFieldsByKey,
        array $orgNames,
        array $personNames,
    ): string {
        $key = $column['key'];

        switch ($key) {
            case 'title':
            case 'purpose_of_activity':
            case 'resources_needed':
                return (string) ($parent->getAttribute($key) ?? '');

            case 'target_date':
                // Matches NewWorkplanHandler's 'M d, Y'.
                return $parent->target_date?->format('M d, Y') ?? (string) $parent->target_date;

            case 'event_location':
                return (string) ($child?->event_location ?? '');

            case 'event_start_time':
            case 'event_end_time':
                return $child?->getAttribute($key)?->format('g:i A') ?? '';

            case 'organization_id':
                return $orgNames[(int) $parent->organization_id] ?? '';

            case 'persons_responsible':
                return collect((array) ($parent->persons_responsible ?? []))
                    ->map(fn ($id) => $personNames[(int) $id] ?? null)
                    ->filter()
                    ->implode(', ');

            default:
                $meta = $eventFieldsByKey[$key] ?? null;
                $type = $meta ? (string) $meta->field_type : FieldType::TEXT;
                $options = $meta ? (array) ($meta->field_options ?? []) : [];

                return SubmissionPresenter::display($submissionPayload, $key, $type, $options);
        }
    }

    /**
     * @param  int[]  $orgIds
     * @return array<int,string>
     */
    private static function orgNames(array $orgIds): array
    {
        if ($orgIds === []) {
            return [];
        }

        return DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('o.organization_id', $orgIds)
            ->pluck('od.name', 'o.organization_id')
            ->map(fn ($name) => (string) ($name ?? ''))
            ->all();
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
