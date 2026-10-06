<?php

namespace App\Services;

use App\Forms\SystemFunction;
use App\Models\AppSetting;
use App\Models\Event;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Decides which concluded events need an After Event Report, and whether a
 * given official may file one.
 *
 * An event is *due* once it ended at least {@see elapsedDays()} days ago (the
 * single admin setting that also triggers notifications) and it ended within
 * the running semester. Events from an elapsed semester drop off the list and
 * can no longer be filed.
 */
class AfterEventReportService
{
    public const SETTING_ELAPSED_DAYS = 'after_event.elapsed_days';

    public const DEFAULT_ELAPSED_DAYS = 3;

    /** Validation error key for "this event already has a report". */
    public const ERROR_ALREADY_FILED = 'already_filed';

    public function elapsedDays(): int
    {
        return max(0, (int) AppSetting::get(self::SETTING_ELAPSED_DAYS, self::DEFAULT_ELAPSED_DAYS));
    }

    /**
     * The bound After Event form (published or not); null when unbound.
     */
    public function form(): ?Form
    {
        return SystemFunction::form(SystemFunction::AFTER_EVENT_REPORT);
    }

    public function enabled(): bool
    {
        return SystemFunction::enabled(SystemFunction::AFTER_EVENT_REPORT);
    }

    /**
     * The running semester: the latest one that has already started. Its
     * window runs from its start to the day before the next semester starts
     * (open-ended when it is the latest semester).
     *
     * @return array{semester: ?Semester, start: ?Carbon, end: ?Carbon}
     */
    public function semesterWindow(): array
    {
        $semester = Semester::query()
            ->where('starts_at', '<=', Carbon::today())
            ->orderByDesc('starts_at')
            ->first();

        return [
            'semester' => $semester,
            'start' => $semester?->starts_at?->copy()->startOfDay(),
            'end' => $semester?->endsAt()?->copy()->endOfDay(),
        ];
    }

    /**
     * Concluded events of the given organizations that are past the elapsed
     * period and inside the running semester, newest first, each annotated with
     * its after-report status.
     *
     * @param  array<int>  $organizationIds
     * @return Collection<int, array<string, mixed>>
     */
    public function eligibleEvents(array $organizationIds, ?Carbon $now = null): Collection
    {
        $organizationIds = array_values(array_filter(array_map('intval', $organizationIds)));
        if ($organizationIds === []) {
            return collect();
        }

        $now ??= now();
        $cutoff = $now->copy()->subDays($this->elapsedDays());
        $window = $this->semesterWindow();

        $query = DB::table('events as e')
            ->join('event_details as ed', 'ed.event_detail_id', '=', 'e.event_detail')
            ->leftJoin('organizations as o', 'o.organization_id', '=', 'e.organization')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('e.organization', $organizationIds)
            ->whereRaw('COALESCE(ed.end_time, ed.start_time) IS NOT NULL')
            ->whereRaw('COALESCE(ed.end_time, ed.start_time) <= ?', [$cutoff->toDateTimeString()])
            ->select([
                'e.event_id',
                'e.organization as organization_id',
                'ed.name',
                'ed.location',
                'ed.start_time',
                DB::raw('COALESCE(ed.end_time, ed.start_time) as finished_at'),
                DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
            ])
            ->orderByRaw('COALESCE(ed.end_time, ed.start_time) DESC');

        if ($window['start'] !== null) {
            $query->whereRaw('COALESCE(ed.end_time, ed.start_time) >= ?', [$window['start']->toDateTimeString()]);
        }

        $rows = $query->get();
        $filed = $this->filedSubmissions($rows->pluck('event_id')->map(fn ($id) => (int) $id)->all());

        return $rows->map(function ($row) use ($filed, $now, $window) {
            $finishedAt = Carbon::parse($row->finished_at);
            $submission = $filed[(int) $row->event_id] ?? null;

            return [
                'event_id' => (int) $row->event_id,
                'organization_id' => (int) $row->organization_id,
                'organization_name' => (string) $row->organization_name,
                'name' => (string) $row->name,
                'location' => (string) ($row->location ?? ''),
                'finished_at' => $finishedAt,
                'time_since' => $finishedAt->diffForHumans($now, ['syntax' => Carbon::DIFF_RELATIVE_TO_NOW]),
                'days_since' => (int) $finishedAt->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()),
                'filed' => $submission !== null,
                'submission_id' => $submission['submission_id'] ?? null,
                'document_id' => $submission['document_id'] ?? null,
                'filed_at' => $submission['submitted_at'] ?? null,
                'deadline' => $window['end'],
            ];
        })->values();
    }

    /**
     * Unfiled due events across the given organizations.
     *
     * @param  array<int>  $organizationIds
     * @return Collection<int, array<string, mixed>>
     */
    public function unfiledEvents(array $organizationIds): Collection
    {
        return $this->eligibleEvents($organizationIds)->reject(fn (array $event) => $event['filed'])->values();
    }

    /**
     * After-event submissions for the given events, keyed by event id, with the
     * first generated document (if any).
     *
     * @param  array<int>  $eventIds
     * @return array<int, array{submission_id:int, document_id:?int, submitted_at:?Carbon}>
     */
    public function filedSubmissions(array $eventIds): array
    {
        $form = $this->form();
        if ($form === null || $eventIds === []) {
            return [];
        }

        $rows = DB::table('form_submissions as fs')
            ->leftJoin('generated_documents as gd', function ($join) {
                $join->on('gd.form_submission_id', '=', 'fs.form_submission_id')
                    ->where('gd.status', '=', 'generated');
            })
            ->where('fs.form_id', (int) $form->getKey())
            ->whereIn('fs.event_id', $eventIds)
            ->orderBy('gd.generated_document_id')
            ->get(['fs.event_id', 'fs.form_submission_id', 'fs.submitted_at', 'gd.document_id']);

        $filed = [];
        foreach ($rows as $row) {
            $eventId = (int) $row->event_id;
            if (isset($filed[$eventId]) && $filed[$eventId]['document_id'] !== null) {
                continue;
            }
            $filed[$eventId] = [
                'submission_id' => (int) $row->form_submission_id,
                'document_id' => $row->document_id !== null ? (int) $row->document_id : null,
                'submitted_at' => $row->submitted_at ? Carbon::parse($row->submitted_at) : null,
            ];
        }

        return $filed;
    }

    public function isFiled(int $eventId): bool
    {
        return $this->filedSubmissions([$eventId]) !== [];
    }

    /**
     * Whether the event has an after-event report other than $exceptSubmissionId
     * (the in-flight submission, which may already carry the event id).
     */
    public function hasOtherReport(int $eventId, ?int $exceptSubmissionId = null): bool
    {
        $form = $this->form();
        if ($form === null) {
            return false;
        }

        return FormSubmission::query()
            ->where('form_id', (int) $form->getKey())
            ->where('event_id', $eventId)
            ->when($exceptSubmissionId, fn ($query) => $query->whereKeyNot($exceptSubmissionId))
            ->exists();
    }

    /**
     * Throw a user-facing validation error unless the user may file the
     * after-event report for this event right now. Returns the event row. An
     * already-reported event fails under {@see ERROR_ALREADY_FILED}; pass the
     * in-flight submission's id so it does not count as that earlier report.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function assertFileable(?User $user, int $eventId, ?int $exceptSubmissionId = null): array
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['form' => $message]);

        $event = Event::query()->find($eventId);
        if ($user === null || $event === null) {
            $fail('That event could not be found.');
        }

        $organizationId = (int) $event->organization;
        $official = DB::table('organization_officers')
            ->where('user', (int) $user->getKey())
            ->where('organization', $organizationId)
            ->whereIn('role', ['officer', 'president'])
            ->exists();
        if (! $official) {
            $fail('Only officials of the organization that held this event can file its after-event report.');
        }

        $row = $this->eligibleEvents([$organizationId])->firstWhere('event_id', $eventId);
        if ($row === null) {
            $fail('This event is not open for an after-event report. Reports open '
                .$this->elapsedDays().' day(s) after an event ends, and close when its semester ends.');
        }
        if ($row['filed'] && $this->hasOtherReport($eventId, $exceptSubmissionId)) {
            throw ValidationException::withMessages([
                self::ERROR_ALREADY_FILED => 'An after-event report has already been filed for “'.$row['name'].'”. Only one report can be filed per event.',
            ]);
        }

        return $row;
    }

    /**
     * The New Event submission an event was created from: Event → EventPlan
     * (event_id) → Request (request_id) → payload.submission_id. Null for
     * events that did not come through the New Event form (e.g. workplan).
     */
    public function sourceSubmission(int $eventId): ?FormSubmission
    {
        $plans = EventPlan::query()
            ->where('event_id', $eventId)
            ->whereNotNull('request_id')
            ->orderByDesc('event_plan_id')
            ->get(['event_plan_id', 'request_id']);

        $newEventForm = SystemFunction::form(SystemFunction::NEW_EVENT);

        foreach ($plans as $plan) {
            $request = ActionRequest::query()->find((int) $plan->request_id);
            $submissionId = (int) (((array) ($request?->payload ?? []))['submission_id'] ?? 0);
            if ($submissionId <= 0) {
                continue;
            }

            $submission = FormSubmission::query()->find($submissionId);
            if ($submission === null) {
                continue;
            }
            if ($newEventForm !== null && (int) $submission->form_id !== (int) $newEventForm->getKey()) {
                continue;
            }

            return $submission;
        }

        return null;
    }

    /**
     * Official users (officer/president, with an email) of an organization.
     *
     * @return Collection<int, User>
     */
    public function officials(int $organizationId): Collection
    {
        $userIds = DB::table('organization_officers')
            ->where('organization', $organizationId)
            ->whereIn('role', ['officer', 'president'])
            ->pluck('user')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        return User::query()->whereIn('user_id', $userIds)->get();
    }
}
