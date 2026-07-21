<?php

namespace App\Http\Controllers\Admin;

use App\Mail\EventApprovedMail;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
use App\Models\User;
use App\Services\DocumentGenerationService;
use App\Helpers\FormTemplateHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class ActivityRequestController extends Controller
{
    private const FORM_ROUTE = 'activity-request';
    private const PAGE_TITLE = 'Activity Requests';

    public function index()
    {
        $form = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::NEW_EVENT);

        if (! $form) {
            return view('pages.admin.activity-requests.index', [
                'title'       => self::PAGE_TITLE,
                'pending'     => collect(),
                'decided'     => collect(),
                'formMissing' => true,
            ]);
        }

        $formId = (int) $form->getKey();

        $requests = ActionRequest::query()
            ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->orderByDesc('requested_at')
            ->limit(300)
            ->get(['request_id', 'action_type', 'organization_id', 'requested_by', 'user', 'payload', 'requested_at'])
            ->filter(fn ($r) => (int) (((array) ($r->payload ?? []))['form_id'] ?? 0) === $formId)
            ->values();

        if ($requests->isEmpty()) {
            return view('pages.admin.activity-requests.index', [
                'title'       => self::PAGE_TITLE,
                'pending'     => collect(),
                'decided'     => collect(),
                'formMissing' => false,
            ]);
        }

        $requestIds = $requests->pluck('request_id')->all();
        $approvals  = Approval::query()->whereIn('request', $requestIds)->get()->keyBy('request');

        $requesterIds = $requests->pluck('user')->filter()->unique()->values()->all();
        $requesterNames = [];
        if (! empty($requesterIds)) {
            $requesterNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $requesterIds)
                ->select('u.user_id', DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
                ->get()->pluck('name', 'user_id')->all();
        }

        $orgIds = $requests->map(fn ($r) => (int) ($r->organization_id ?? (((array) ($r->payload ?? []))['organization_id'] ?? 0)))->filter(fn ($id) => $id > 0)->unique()->values()->all();
        $orgNames = [];
        if (! empty($orgIds)) {
            $orgNames = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $orgIds)
                ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                ->get()->pluck('name', 'organization_id')->all();
        }

        $eventPlansByRequestId = EventPlan::query()
            ->whereIn('request_id', $requestIds)
            ->get(['event_plan_id', 'request_id', 'title'])
            ->keyBy('request_id');

        $rows = $requests->map(function ($r) use ($approvals, $requesterNames, $orgNames, $eventPlansByRequestId) {
            $payload = (array) ($r->payload ?? []);
            $orgId   = (int) ($r->organization_id ?? ($payload['organization_id'] ?? 0));
            $submissionId = (int) ($payload['submission_id'] ?? 0);
            $activityTitle = '';

            if ($submissionId > 0) {
                $sub = FormSubmission::query()->find($submissionId);
                if ($sub) {
                    $activityTitle = (string) (((array) ($sub->payload ?? []))['projectActivity'] ?? '');
                }
            }

            if ($activityTitle === '') {
                $plan = $eventPlansByRequestId->get((int) $r->request_id);
                $activityTitle = $plan?->title ?? '—';
            }

            return [
                'request'        => $r,
                'approval'       => $approvals->get($r->request_id),
                'requester_name' => $requesterNames[$r->user] ?? 'Unknown',
                'org_name'       => $orgNames[$orgId] ?? 'Unknown Organization',
                'activity_title' => $activityTitle,
                'submission_id'  => $submissionId,
            ];
        });

        return view('pages.admin.activity-requests.index', [
            'title'       => self::PAGE_TITLE,
            'pending'     => $rows->filter(fn ($row) => $row['approval'] === null)->values(),
            'decided'     => $rows->filter(fn ($row) => $row['approval'] !== null)->take(30)->values(),
            'formMissing' => false,
        ]);
    }

    public function show(int $requestId)
    {
        $actionRequest = ActionRequest::query()->findOrFail($requestId);
        $payload       = (array) ($actionRequest->payload ?? []);
        $submissionId  = (int) ($payload['submission_id'] ?? 0);
        $submission    = $submissionId ? FormSubmission::query()->find($submissionId) : null;

        // Render answers from the submitted form's own fields so the details
        // show regardless of the builder form's field keys (the new_event form
        // is admin-authored, not the old hardcoded activity-request schema).
        $form   = $submission?->form
            ?: \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::NEW_EVENT);
        $fields = $form ? $form->fields()->get() : collect();

        $approval = Approval::query()->where('request', $requestId)->first();

        $orgId   = (int) ($actionRequest->organization_id ?? ($payload['organization_id'] ?? 0));
        $orgName = 'Unknown Organization';
        if ($orgId > 0) {
            $orgRow  = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->where('o.organization_id', $orgId)
                ->select(DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                ->first();
            $orgName = $orgRow?->name ?? 'Unknown Organization';
        }

        $requesterName = 'Unknown';
        if ($actionRequest->user) {
            $profileRow = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->where('u.user_id', $actionRequest->user)
                ->select(DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
                ->first();
            $requesterName = $profileRow?->name ?? 'Unknown';
        }

        return view('pages.admin.activity-requests.show', [
            'title'           => self::PAGE_TITLE,
            'actionRequest'   => $actionRequest,
            'submission'      => $submission,
            'submissionPayload' => $submission ? (array) ($submission->payload ?? []) : [],
            'fields'          => $fields,
            'approval'        => $approval,
            'orgName'         => $orgName,
            'requesterName'   => $requesterName,
        ]);
    }

    public function decide(Request $request, int $requestId, DocumentGenerationService $documentGenerationService): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $userId = (int) $user->getKey();

        $validated = $request->validate([
            'decision'         => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $actionRequest = ActionRequest::query()->findOrFail($requestId);

        $form = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::NEW_EVENT);
        if (! $form) {
            return back()->withErrors(['form' => 'Activity request form is not configured.']);
        }

        $payload = (array) ($actionRequest->payload ?? []);
        if ((int) ($payload['form_id'] ?? 0) !== (int) $form->getKey()) {
            return back()->withErrors(['request' => 'This request does not belong to the activity request form.']);
        }

        $isApproved         = $validated['decision'] === 'approve';
        $generatedDocumentId = null;

        if ($isApproved) {
            try {
                $generatedDocument   = $documentGenerationService->generateFromApprovedRequest($actionRequest, $userId);
                $generatedDocumentId = (int) $generatedDocument->getKey();
            } catch (\Throwable $throwable) {
                return back()->withErrors(['request' => $throwable->getMessage()]);
            }
        }

        $approval = Approval::query()->updateOrCreate(
            ['request' => $requestId],
            [
                'admin'            => $userId,
                'approved_at'      => now(),
                'is_rejected'      => ! $isApproved,
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]
        );

        if ($generatedDocumentId) {
            GeneratedDocument::where('generated_document_id', $generatedDocumentId)
                ->update(['approval_id' => (int) $approval->approval_id]);
        }

        $eventPlan = EventPlan::query()->where('request_id', $requestId)->first();

        if ($eventPlan && $isApproved) {
            $eventDetail = EventDetail::query()->create([
                'name'       => (string) ($eventPlan->title ?? ''),
                'location'   => (string) ($eventPlan->event_location ?? ''),
                'desc_text'  => '',
                'start_time' => $eventPlan->event_start_time,
                'end_time'   => $eventPlan->event_end_time,
            ]);

            $event = Event::query()->create([
                'organization' => (int) $eventPlan->organization_id,
                'creator'      => $userId,
                'event_detail' => (int) $eventDetail->getKey(),
            ]);

            $eventId = (int) $event->getKey();

            $eventPlan->update(['status' => 'approved', 'event_id' => $eventId]);

            if ($eventPlan->parent_plan_id) {
                EventPlan::query()
                    ->where('event_plan_id', $eventPlan->parent_plan_id)
                    ->update(['event_id' => $eventId]);
            }

            $requester = User::query()->find((int) $eventPlan->created_by);
            if ($requester?->user_email) {
                try {
                    Mail::to($requester->user_email)
                        ->send(new EventApprovedMail($eventPlan, $this->resolveUserName((int) $eventPlan->created_by)));
                } catch (\Throwable) {
                }
            }
        } elseif ($eventPlan) {
            $eventPlan->update(['status' => 'rejected']);
        }

        $message = $isApproved
            ? 'Activity request approved and document generated.'
            : 'Activity request rejected.';

        return redirect()->route('admin.activity-requests.index')->with('success', $message);
    }

    private function resolveUserName(int $userId): string
    {
        $profileRow = DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('u.user_id', $userId)
            ->select(['p.first_name', 'p.middle_name', 'p.last_name'])
            ->first();

        if (! $profileRow) {
            return 'Officer';
        }

        return trim(implode(' ', array_filter([
            $profileRow->first_name,
            $profileRow->middle_name,
            $profileRow->last_name,
        ]))) ?: 'Officer';
    }
}
