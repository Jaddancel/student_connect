<?php

namespace App\Services\Scoring;

use App\Forms\SystemFunction;
use App\Helpers\FormTemplateHelper;
use App\Models\Profile;
use App\Models\ScoringRule;
use App\Models\Semester;
use App\Services\AfterEventReportService;
use App\Support\UniversalField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluates admin-authored scoring triggers (see {@see TriggerValidator} for
 * the AST grammar) over an organization's APPROVED records for a semester:
 * approved event plans, and approved submissions of whichever forms the rules
 * watch (After Event reports have no approval step, so every filed report
 * counts). Returns instance counts per criterion key — the same "tally" numbers
 * the legacy hardcoded conditions produce, so the scoring controller can
 * overlay them criterion-by-criterion.
 */
class ScoringRuleEngine
{
    /**
     * @return array<string,int> criterion key => tallied instances (only for
     *                           criteria that have an enabled rule)
     */
    public function instancesFor(int $organizationId, Semester $semester): array
    {
        if (! Schema::hasTable('scoring_rules')) {
            return [];
        }

        $rules = ScoringRule::query()
            ->with('criterion')
            ->where('enabled', true)
            ->get()
            ->filter(fn (ScoringRule $rule) => $rule->criterion && $rule->criterion->is_active);

        if ($rules->isEmpty()) {
            return [];
        }

        $semesterStart = $semester->starts_at;
        $semesterEnd = $semester->endsAt() ?? now();

        // Build each record set once, shared across rules.
        $planRecords = null;
        $submissionRecords = [];

        $instances = [];
        foreach ($rules as $rule) {
            $trigger = (array) $rule->trigger;
            $when = (array) ($trigger['when'] ?? []);
            $source = (string) ($when['source'] ?? '');

            try {
                if ($source === 'event_plan') {
                    $planRecords ??= $this->approvedEventPlans($organizationId, $semesterStart, $semesterEnd);
                    $records = $planRecords;
                } elseif ($source === 'form_submission') {
                    $formId = (int) ($when['form_id'] ?? 0);
                    $submissionRecords[$formId] ??= $this->approvedSubmissions($formId, $organizationId, $semesterStart, $semesterEnd);
                    $records = $submissionRecords[$formId];
                } else {
                    continue;
                }

                $total = 0;
                foreach ($records as $record) {
                    if ($this->passes($trigger['if'] ?? null, $record)) {
                        $total += $this->contribution((array) ($trigger['then']['add'] ?? []), $record);
                    }
                }

                $instances[$rule->criterion->key] = $total;
            } catch (\Throwable $e) {
                // A broken rule must never take scoring down; skip it loudly.
                Log::warning('Scoring rule evaluation failed', [
                    'criterion' => $rule->criterion->key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $instances;
    }

    /**
     * Approved event plans in the semester, with `members_attended` joined in
     * from approved accomplishment reports (mirrors the legacy engine's data).
     *
     * @return Collection<int,array{values:array<string,mixed>, profile:?Profile}>
     */
    private function approvedEventPlans(int $organizationId, $start, $end): Collection
    {
        $plans = DB::table('event_plans as ep')
            ->join('requests as r', 'r.request_id', '=', 'ep.request_id')
            ->join('approvals as a', 'a.request', '=', 'r.request_id')
            ->where('ep.organization_id', $organizationId)
            ->where('a.is_rejected', false)
            ->whereNotNull('a.approved_at')
            ->whereBetween('a.approved_at', [$start, $end])
            ->select([
                'ep.event_plan_id', 'ep.event_id', 'ep.title', 'ep.target_date',
                'ep.activity_types', 'ep.seminar_level', 'ep.related_to_organization',
                'ep.extension_services', 'ep.sponsor', 'ep.cosponsor_count',
                'ep.area_scope', 'ep.created_by',
            ])
            ->get();

        $membersByEvent = $this->membersAttendedByEvent($organizationId, $start, $end);

        return $plans->map(function ($p) use ($membersByEvent) {
            return [
                'values' => [
                    'title' => $p->title,
                    'target_date' => $p->target_date,
                    'activity_types' => $p->activity_types ? (json_decode($p->activity_types, true) ?: []) : [],
                    'seminar_level' => $p->seminar_level,
                    'related_to_organization' => (bool) $p->related_to_organization,
                    'extension_services' => (bool) $p->extension_services,
                    'sponsor' => $p->sponsor,
                    'cosponsor_count' => (int) ($p->cosponsor_count ?? 0),
                    'area_scope' => $p->area_scope,
                    'members_attended' => $membersByEvent[(int) ($p->event_id ?? 0)] ?? 0,
                ],
                'user_id' => (int) ($p->created_by ?? 0),
            ];
        });
    }

    /**
     * Approved submissions of one form in the semester (approval = the
     * submission's document-generation request was approved — the same
     * linkage the legacy engine uses). After Event reports skip approval, so
     * every filed one counts, carrying the values of the New Event submission
     * that created its event (`new_event:<key>` variables).
     *
     * @return Collection<int,array{values:array<string,mixed>, user_id:int, linked_values?:array<string,mixed>}>
     */
    private function approvedSubmissions(int $formId, int $organizationId, $start, $end): Collection
    {
        if ($formId <= 0) {
            return collect();
        }

        $submissions = DB::table('form_submissions as fs')
            ->where('fs.form_id', $formId)
            ->where('fs.organization_id', $organizationId)
            ->whereBetween('fs.submitted_at', [$start, $end])
            ->select(['fs.form_submission_id', 'fs.submitted_by', 'fs.payload', 'fs.event_id'])
            ->get();

        if ($submissions->isEmpty()) {
            return collect();
        }

        if ($formId === (int) (SystemFunction::form(SystemFunction::AFTER_EVENT_REPORT)?->getKey() ?? 0)) {
            return $this->filedAfterEventReports($submissions);
        }

        $submissionIds = $submissions->pluck('form_submission_id')->map(fn ($id) => (int) $id)->all();

        $approvedIds = DB::table('requests as r')
            ->join('approvals as a', 'a.request', '=', 'r.request_id')
            ->where('r.action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->where('r.organization_id', $organizationId)
            ->where('a.is_rejected', false)
            ->whereNotNull('a.approved_at')
            ->get(['r.payload'])
            ->map(fn ($row) => (int) ((is_string($row->payload) ? json_decode($row->payload, true) : (array) $row->payload)['submission_id'] ?? 0))
            ->filter(fn ($id) => in_array($id, $submissionIds, true))
            ->all();

        return $submissions
            ->filter(fn ($s) => in_array((int) $s->form_submission_id, $approvedIds, true))
            ->map(fn ($s) => [
                'values' => is_string($s->payload) ? (json_decode($s->payload, true) ?: []) : (array) $s->payload,
                'user_id' => (int) ($s->submitted_by ?? 0),
            ])
            ->values();
    }

    /**
     * @param  Collection<int,object>  $submissions
     * @return Collection<int,array{values:array<string,mixed>, user_id:int, linked_values:array<string,mixed>}>
     */
    private function filedAfterEventReports(Collection $submissions): Collection
    {
        $service = app(AfterEventReportService::class);
        $linked = [];

        return $submissions
            ->map(function ($s) use ($service, &$linked) {
                $eventId = (int) ($s->event_id ?? 0);
                if ($eventId > 0 && ! array_key_exists($eventId, $linked)) {
                    $payload = $service->sourceSubmission($eventId)?->payload;
                    $linked[$eventId] = is_string($payload) ? (json_decode($payload, true) ?: []) : (array) ($payload ?? []);
                }

                return [
                    'values' => is_string($s->payload) ? (json_decode($s->payload, true) ?: []) : (array) $s->payload,
                    'user_id' => (int) ($s->submitted_by ?? 0),
                    'linked_values' => $eventId > 0 ? $linked[$eventId] : [],
                ];
            })
            ->values();
    }

    /**
     * members_attended per event_id from approved accomplishment reports —
     * exposed to event-plan rules as `plan:members_attended`.
     *
     * @return array<int,int>
     */
    private function membersAttendedByEvent(int $organizationId, $start, $end): array
    {
        $arForm = \App\Models\Form::query()->where('route_name', 'accomplishment-report')->first();
        if (! $arForm) {
            return [];
        }

        $rows = $this->approvedSubmissions((int) $arForm->getKey(), $organizationId, $start, $end);

        $map = [];
        foreach ($rows as $row) {
            $eventId = (int) ($row['values']['event_id'] ?? 0);
            if ($eventId > 0) {
                $map[$eventId] = (int) ($row['values']['members_attended'] ?? 0);
            }
        }

        // The legacy engine reads event_id off the submission row itself.
        $withEventIds = DB::table('form_submissions')
            ->where('form_id', (int) $arForm->getKey())
            ->where('organization_id', $organizationId)
            ->whereNotNull('event_id')
            ->whereBetween('submitted_at', [$start, $end])
            ->get(['event_id', 'payload']);
        foreach ($withEventIds as $sub) {
            $p = is_string($sub->payload) ? (json_decode($sub->payload, true) ?: []) : (array) $sub->payload;
            $map[(int) $sub->event_id] ??= (int) ($p['members_attended'] ?? 0);
        }

        return $map;
    }

    /**
     * @param  array{values:array<string,mixed>, user_id:int}  $record
     */
    private function passes(?array $condition, array $record): bool
    {
        if ($condition === null) {
            return true;
        }

        $op = $condition['op'] ?? null;

        if ($op === 'and') {
            foreach ((array) ($condition['children'] ?? []) as $child) {
                if (! $this->passes((array) $child, $record)) {
                    return false;
                }
            }

            return true;
        }

        if ($op === 'or') {
            foreach ((array) ($condition['children'] ?? []) as $child) {
                if ($this->passes((array) $child, $record)) {
                    return true;
                }
            }

            return false;
        }

        if ($op === 'not') {
            $child = ((array) ($condition['children'] ?? []))[0] ?? null;

            return $child === null || ! $this->passes((array) $child, $record);
        }

        $left = $this->resolve((string) ($condition['left']['var'] ?? ''), $record);

        if ($op === 'not_empty') {
            return ! ($left === null || $left === '' || $left === []);
        }

        $right = $condition['right']['value'] ?? null;

        return match ($op) {
            '=' => $this->looselyEquals($left, $right),
            '!=' => ! $this->looselyEquals($left, $right),
            '>' => (float) $this->numeric($left) > (float) $right,
            '>=' => (float) $this->numeric($left) >= (float) $right,
            '<' => (float) $this->numeric($left) < (float) $right,
            '<=' => (float) $this->numeric($left) <= (float) $right,
            'contains' => is_array($left)
                ? in_array($right, $left, false)
                : str_contains(mb_strtolower((string) $left), mb_strtolower((string) $right)),
            default => false,
        };
    }

    /**
     * @param  array{values:array<string,mixed>, user_id:int}  $record
     */
    private function contribution(array $add, array $record): int
    {
        return match ($add['kind'] ?? null) {
            'const' => max(0, (int) ($add['value'] ?? 0)),
            'floor_div' => (function () use ($add, $record): int {
                $divisor = (float) ($add['divisor'] ?? 0);
                if ($divisor <= 0) {
                    return 0;
                }
                $value = $this->numeric($this->resolve((string) ($add['var'] ?? ''), $record));

                return max(0, (int) floor($value / $divisor));
            })(),
            'count_list' => (function () use ($add, $record): int {
                $value = $this->resolve((string) ($add['var'] ?? ''), $record);
                if (is_string($value)) {
                    $value = json_decode($value, true) ?? [];
                }

                return $this->rowCount((array) $value);
            })(),
            default => 0,
        };
    }

    /**
     * Resolve a `prefix:key` variable against a record. `field:`/`plan:` read
     * the record's own values; `new_event:` reads the linked New Event
     * submission (After Event reports); `universal:` reads the acting user's
     * profile.
     *
     * @param  array{values:array<string,mixed>, user_id:int}  $record
     */
    private function resolve(string $var, array $record): mixed
    {
        [$prefix, $key] = array_pad(explode(':', $var, 2), 2, '');

        if ($prefix === 'field' || $prefix === 'plan') {
            return $record['values'][$key] ?? null;
        }

        if ($prefix === 'new_event') {
            return $record['linked_values'][$key] ?? null;
        }

        if ($prefix === 'universal') {
            $userId = (int) ($record['user_id'] ?? 0);
            if ($userId <= 0) {
                return null;
            }
            static $profiles = [];
            if (! array_key_exists($userId, $profiles)) {
                $profileId = DB::table('users')->where('user_id', $userId)->value('profile');
                $profiles[$userId] = $profileId ? Profile::query()->find($profileId) : null;
            }

            return $profiles[$userId] ? UniversalField::valueFor($profiles[$userId], $key) : null;
        }

        return null;
    }

    private function looselyEquals(mixed $left, mixed $right): bool
    {
        // Normalize the common yes/no + boolean shapes forms produce.
        $normalize = function (mixed $v): mixed {
            if (is_bool($v)) {
                return $v ? '1' : '0';
            }
            $s = mb_strtolower(trim((string) (is_scalar($v) ? $v : json_encode($v))));

            return match ($s) {
                'yes', 'true' => '1',
                'no', 'false' => '0',
                default => $s,
            };
        };

        if (is_array($left)) {
            return in_array($right, $left, false);
        }

        return $normalize($left) === $normalize($right);
    }

    /**
     * Non-blank rows of a list value (text list, table, multi-image, …).
     *
     * @param  array<int|string,mixed>  $rows
     */
    private function rowCount(array $rows): int
    {
        return count(array_filter($rows, fn ($v) => trim((string) (is_scalar($v) ? $v : json_encode($v))) !== ''));
    }

    private function numeric(mixed $value): float
    {
        // A list compares (and divides) by its number of rows, so
        // "Faculty Advisers ≥ 2" means "at least two advisers listed".
        if (is_array($value)) {
            return (float) $this->rowCount($value);
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        // Tolerate currency-ish strings ("₱1,500.00").
        $clean = preg_replace('/[^0-9.\-]/', '', (string) (is_scalar($value) ? $value : ''));

        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
