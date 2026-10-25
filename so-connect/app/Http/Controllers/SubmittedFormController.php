<?php

namespace App\Http\Controllers;

use App\Forms\SystemFunction;
use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubmittedFormController extends Controller
{
    private const TYPES = [
        'all' => 'All forms',
        'event' => 'Event',
        'membership' => 'Membership',
        'accreditation' => 'Accreditation',
        'registration' => 'Registration',
        'general' => 'General',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && (int) $user->user_type === User::TYPE_OFFICER, 403);

        $selectedType = array_key_exists((string) $request->query('type'), self::TYPES)
            ? (string) $request->query('type')
            : 'all';

        $baseQuery = FormSubmission::query()
            ->where('submitted_by', (int) $user->getKey());

        $counts = collect(array_keys(self::TYPES))->mapWithKeys(function (string $type) use ($baseQuery) {
            $query = clone $baseQuery;
            $this->applyTypeFilter($query, $type);

            return [$type => $query->count()];
        })->all();

        $query = (clone $baseQuery)
            ->with([
                'form.category',
                'organization.detailRecord',
                'event.detailOfEvent',
                'generatedDocuments',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('form_submission_id');
        $this->applyTypeFilter($query, $selectedType);

        $submissions = $query->paginate(15)->withQueryString();
        $submissionIds = $submissions->getCollection()
            ->pluck('form_submission_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $actionRequests = ActionRequest::query()
            ->where('requested_by', (int) $user->getKey())
            ->orderByDesc('requested_at')
            ->get();
        $approvals = Approval::query()
            ->whereIn('request', $actionRequests->pluck('request_id'))
            ->get()
            ->keyBy(fn (Approval $approval) => (int) $approval->request);
        $actionRequests->each(fn (ActionRequest $actionRequest) => $actionRequest->setRelation(
            'approval',
            $approvals->get((int) $actionRequest->getKey()),
        ));

        $requests = $actionRequests
            ->mapWithKeys(function (ActionRequest $actionRequest) use ($submissionIds) {
                $payload = (array) ($actionRequest->payload ?? []);
                $submissionId = (int) ($payload['submission_id'] ?? $payload['form_submission_id'] ?? 0);

                return $submissionId > 0 && in_array($submissionId, $submissionIds, true)
                    ? [$submissionId => $actionRequest]
                    : [];
            });

        $submissions->through(function (FormSubmission $submission) use ($requests) {
            $type = $this->typeFor($submission->form);
            $actionRequest = $requests->get((int) $submission->getKey());

            return [
                'id' => (int) $submission->getKey(),
                'form_name' => $submission->form->name,
                'type' => $type,
                'type_label' => self::TYPES[$type],
                'context' => $this->contextFor($submission, $type),
                'organization' => $submission->organization?->detailRecord?->name ?? 'No organization',
                'submitted_at' => $submission->submitted_at,
                'status' => $this->statusFor($actionRequest),
                'document_count' => $submission->generatedDocuments->count(),
            ];
        });

        return view('pages.submitted-forms.index', [
            'title' => 'Submitted Forms',
            'submissions' => $submissions,
            'types' => self::TYPES,
            'counts' => $counts,
            'selectedType' => $selectedType,
        ]);
    }

    private function applyTypeFilter(Builder $query, string $type): void
    {
        if ($type === 'all') {
            return;
        }

        $eventFunctions = [SystemFunction::NEW_EVENT, SystemFunction::NEW_WORKPLAN, SystemFunction::AFTER_EVENT_REPORT];
        $registrationFunctions = [SystemFunction::SIGN_UP, SystemFunction::NEW_ORGANIZATION_REGISTRATION];

        $query->whereHas('form', function (Builder $formQuery) use ($type, $eventFunctions, $registrationFunctions) {
            match ($type) {
                'event' => $formQuery->where(function (Builder $candidate) use ($eventFunctions) {
                    $candidate->whereIn('system_function', $eventFunctions)
                        ->orWhereHas('category', fn (Builder $category) => $category->whereIn('slug', ['activity', 'accomplishment']));
                }),
                'membership' => $formQuery->where(function (Builder $candidate) {
                    $candidate->where('system_function', SystemFunction::MEMBERSHIP_REGISTRATION)
                        ->orWhereHas('category', fn (Builder $category) => $category->where('slug', 'membership'));
                }),
                'accreditation' => $formQuery->where(function (Builder $candidate) {
                    $candidate->where('system_function', SystemFunction::ORG_ACCREDITATION)
                        ->orWhereHas('category', fn (Builder $category) => $category->where('slug', 'accreditation'));
                }),
                'registration' => $formQuery->whereIn('system_function', $registrationFunctions),
                default => $formQuery
                    ->where(function (Builder $candidate) use ($eventFunctions, $registrationFunctions) {
                        $candidate->whereNull('system_function')
                            ->orWhereNotIn('system_function', [
                                ...$eventFunctions,
                                SystemFunction::MEMBERSHIP_REGISTRATION,
                                SystemFunction::ORG_ACCREDITATION,
                                ...$registrationFunctions,
                            ]);
                    })
                    ->whereDoesntHave('category', fn (Builder $category) => $category->whereIn('slug', [
                        'activity', 'accomplishment', 'membership', 'accreditation',
                    ])),
            };
        });
    }

    private function typeFor(Form $form): string
    {
        $systemFunction = (string) ($form->system_function ?? '');
        $category = (string) ($form->category?->slug ?? '');

        if (in_array($systemFunction, [SystemFunction::NEW_EVENT, SystemFunction::NEW_WORKPLAN, SystemFunction::AFTER_EVENT_REPORT], true)
            || in_array($category, ['activity', 'accomplishment'], true)) {
            return 'event';
        }
        if ($systemFunction === SystemFunction::MEMBERSHIP_REGISTRATION || $category === 'membership') {
            return 'membership';
        }
        if ($systemFunction === SystemFunction::ORG_ACCREDITATION || $category === 'accreditation') {
            return 'accreditation';
        }
        if (in_array($systemFunction, [SystemFunction::SIGN_UP, SystemFunction::NEW_ORGANIZATION_REGISTRATION], true)) {
            return 'registration';
        }

        return 'general';
    }

    private function contextFor(FormSubmission $submission, string $type): string
    {
        if ($type !== 'event') {
            return match ($type) {
                'membership' => 'Membership process',
                'accreditation' => 'Accreditation process',
                'registration' => 'Organization registration',
                default => 'Not event-related',
            };
        }

        $eventName = trim((string) ($submission->event?->detailOfEvent?->name ?? ''));
        if ($eventName !== '') {
            return $eventName;
        }

        $payload = (array) ($submission->payload ?? []);
        foreach (['title', 'projectActivity', 'event_title', 'event_name'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $rows = collect($payload['workplan_rows'] ?? $payload['activities'] ?? [])
            ->map(fn ($row) => trim((string) (is_array($row) ? ($row['title'] ?? '') : $row)))
            ->filter()
            ->take(3)
            ->values();

        return $rows->isNotEmpty() ? $rows->implode(', ') : 'Event details pending';
    }

    /** @return array{key:string,label:string} */
    private function statusFor(?ActionRequest $actionRequest): array
    {
        if (! $actionRequest) {
            return ['key' => 'submitted', 'label' => 'Submitted'];
        }
        if (! $actionRequest->approval) {
            return ['key' => 'pending', 'label' => 'Pending OSSD review'];
        }
        if ($actionRequest->approval->is_rejected) {
            return ['key' => 'rejected', 'label' => 'Rejected'];
        }

        return ['key' => 'approved', 'label' => 'Approved'];
    }
}
