<?php

namespace App\Http\Controllers\Admin;

use App\Forms\Handlers\NewOrganizationRegistrationHandler;
use App\Forms\SystemFunction;
use App\Helpers\FormTemplateHelper;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Services\RequestApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The generic per-form admin request page: every form page has its own
 * request queue (Requests → "<Form name>"), listing the requests its
 * submissions created — document-generation requests for plain forms,
 * membership requests for a membership-bound form — keyed by the indexed
 * `requests.form_id` column.
 *
 * Decisions delegate to {@see RequestApprovalService}, whose action_type-first
 * dispatch performs the right side effects for either kind on one code path.
 * Forms bound to sign-up / new-event / new-workplan have dedicated flows and
 * 404 here.
 */
class FormRequestController extends Controller
{
    /** Request kinds a form page can originate (membership, doc-gen, new org). */
    private const ACTION_TYPES = [
        1,
        FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
        \App\Forms\Handlers\NewOrganizationRegistrationHandler::ACTION_TYPE,
    ];

    public function index(Form $form)
    {
        $this->assertHasRequestPage($form);

        $requests = ActionRequest::query()
            ->where('form_id', (int) $form->getKey())
            ->whereIn('action_type', self::ACTION_TYPES)
            ->orderByDesc('requested_at')
            ->limit(300)
            ->get(['request_id', 'action_type', 'organization_id', 'requested_by', 'user', 'payload', 'requested_at']);

        $approvals = $requests->isEmpty()
            ? collect()
            : Approval::query()->whereIn('request', $requests->pluck('request_id'))->get()->groupBy('request');

        $requesterNames = $this->requesterNames($requests->pluck('user')->filter()->unique()->values()->all());
        $orgNames = $this->organizationNames(
            $requests->map(fn ($r) => $this->displayOrganizationId($r, (array) ($r->payload ?? [])))
                ->filter(fn ($id) => $id > 0)->unique()->values()->all()
        );

        $rows = $requests->map(function ($r) use ($approvals, $requesterNames, $orgNames) {
            $payload = (array) ($r->payload ?? []);
            $orgId = $this->displayOrganizationId($r, $payload);
            $group = $approvals->get($r->request_id) ?? collect();
            $isMembership = (int) $r->action_type === 1;

            // Membership is two-stage: the president approves first, then the
            // admin here casts the final approval that triggers enrollment.
            $decisionApproval = $isMembership ? $group->firstWhere('stage', 'admin') : $group->first();
            $presidentApproval = $isMembership ? $group->firstWhere('stage', 'president') : null;
            $isDecided = $decisionApproval !== null
                || ($presidentApproval !== null && (bool) $presidentApproval->is_rejected);

            return [
                'request' => $r,
                'approval' => $decisionApproval,
                'president_approval' => $presidentApproval,
                'is_decided' => $isDecided,
                'requester_name' => $requesterNames[$r->user] ?? 'Unknown',
                'org_name' => $orgNames[$orgId] ?? 'Unknown Organization',
                'submission_id' => (int) ($payload['submission_id'] ?? ($payload['form_submission_id'] ?? 0)),
                'kind' => match ((int) $r->action_type) {
                    1 => 'Membership',
                    NewOrganizationRegistrationHandler::ACTION_TYPE => 'New Organization',
                    default => 'Document',
                },
            ];
        });

        return view('pages.admin.form-requests.index', [
            'title' => $form->name.' Requests',
            'form' => $form,
            'orgColumnLabel' => (string) $form->system_function === SystemFunction::MEMBERSHIP_REGISTRATION
                ? "Requester's Organization"
                : 'Organization',
            'pending' => $rows->filter(fn ($row) => ! $row['is_decided'])->values(),
            'decided' => $rows->filter(fn ($row) => $row['is_decided'])->take(30)->values(),
        ]);
    }

    public function show(Form $form, int $requestId)
    {
        $this->assertHasRequestPage($form);
        $actionRequest = $this->requestForForm($form, $requestId);

        $payload = (array) ($actionRequest->payload ?? []);
        $submissionId = (int) ($payload['submission_id'] ?? ($payload['form_submission_id'] ?? 0));
        $submission = $submissionId ? FormSubmission::query()->find($submissionId) : null;

        $orgId = $this->displayOrganizationId($actionRequest, $payload);
        $orgNames = $orgId > 0 ? $this->organizationNames([$orgId]) : [];
        $requesterNames = $actionRequest->user ? $this->requesterNames([(int) $actionRequest->user]) : [];

        $newOrganizationEmailStatuses = [];
        if ((string) $form->system_function === SystemFunction::NEW_ORGANIZATION_REGISTRATION && $submission) {
            $handler = app(NewOrganizationRegistrationHandler::class);
            $submissionPayload = (array) ($submission->payload ?? []);
            $emails = ['president' => $handler->resolvePresidentEmail($form, $submissionPayload)];
            foreach ($handler->resolveOfficerEmails($form, $submissionPayload) as $index => $email) {
                $emails['officer '.($index + 1)] = $email;
            }

            foreach ($emails as $role => $email) {
                if ($email !== null && $email !== '') {
                    $newOrganizationEmailStatuses[$role] = [
                        'email' => $email,
                        'registered' => $handler->isRegisteredEmail($email),
                    ];
                }
            }
        }

        $isMembership = (int) $actionRequest->action_type === 1;
        $presidentApproval = $isMembership
            ? Approval::query()->where('request', $requestId)->where('stage', 'president')->first()
            : null;
        // Membership needs the admin (final) approval; other kinds are single-stage.
        $decisionApproval = $isMembership
            ? Approval::query()->where('request', $requestId)->where('stage', 'admin')->first()
            : Approval::query()->where('request', $requestId)->first();

        return view('pages.admin.form-requests.show', [
            'title' => $form->name.' Requests',
            'form' => $form,
            'fields' => $form->fields()->get(),
            'actionRequest' => $actionRequest,
            'submission' => $submission,
            'submissionPayload' => $submission ? (array) ($submission->payload ?? []) : [],
            'approval' => $decisionApproval,
            'presidentApproval' => $presidentApproval,
            'orgName' => $orgNames[$orgId] ?? 'Unknown Organization',
            'requesterName' => $requesterNames[$actionRequest->user] ?? 'Unknown',
            'kind' => (int) $actionRequest->action_type === 1 ? 'Membership' : ((int) $actionRequest->action_type === NewOrganizationRegistrationHandler::ACTION_TYPE ? 'New Organization' : 'Document'),
            'newOrganizationEmailStatuses' => $newOrganizationEmailStatuses,
        ]);
    }

    public function decide(Request $request, Form $form, int $requestId, RequestApprovalService $approvalService): RedirectResponse
    {
        $this->assertHasRequestPage($form);

        $user = $request->user();
        abort_unless((bool) $user, 401);
        $userId = (int) $user->getKey();

        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $actionRequest = $this->requestForForm($form, $requestId);

        $stage = ((int) $actionRequest->action_type === 1) ? 'admin' : null;

        if ($validated['decision'] === 'approve') {
            try {
                $approvalService->approve($actionRequest, $userId, $stage);
            } catch (\Throwable $throwable) {
                return back()->withErrors(['request' => $throwable->getMessage()]);
            }

            return redirect()->route('admin.form-requests.index', $form)
                ->with('success', 'Request approved.');
        }

        $approvalService->reject($actionRequest, $userId, $validated['rejection_reason'] ?? null, $stage);

        return redirect()->route('admin.form-requests.index', $form)
            ->with('success', 'Request rejected — the requester can submit the form again.');
    }

    /** Forms bound to the excepted system functions have dedicated flows. */
    private function assertHasRequestPage(Form $form): void
    {
        abort_if(in_array((string) $form->system_function, [
            SystemFunction::SIGN_UP,
            SystemFunction::NEW_EVENT,
            SystemFunction::NEW_WORKPLAN,
        ], true), 404);
    }

    private function requestForForm(Form $form, int $requestId): ActionRequest
    {
        return ActionRequest::query()
            ->whereKey($requestId)
            ->where('form_id', (int) $form->getKey())
            ->whereIn('action_type', self::ACTION_TYPES)
            ->firstOrFail();
    }

    /**
     * The org to display for a request. Membership requests show the org the
     * requester belongs to (captured at submission, else their membership),
     * not the receiving org the request was sent to; other kinds show their
     * own organization.
     */
    private function displayOrganizationId(object $actionRequest, array $payload): int
    {
        $receivingOrgId = (int) ($actionRequest->organization_id ?? ($payload['organization_id'] ?? 0));

        if ((int) $actionRequest->action_type !== 1) {
            return $receivingOrgId;
        }

        $requesterOrgId = (int) ($payload['requester_organization_id'] ?? 0);
        if ($requesterOrgId <= 0) {
            $requesterOrgId = (int) (\Illuminate\Support\Facades\DB::table('organization_officers')
                ->where('user', (int) $actionRequest->user)
                ->orderByRaw("FIELD(role, 'president') DESC")
                ->value('organization') ?? 0);
        }

        return $requesterOrgId > 0 ? $requesterOrgId : $receivingOrgId;
    }

    /**
     * @param  array<int,int>  $userIds
     * @return array<int,string>
     */
    private function requesterNames(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('u.user_id', $userIds)
            ->select('u.user_id', \Illuminate\Support\Facades\DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
            ->get()->pluck('name', 'user_id')->all();
    }

    /**
     * @param  array<int,int>  $orgIds
     * @return array<int,string>
     */
    private function organizationNames(array $orgIds): array
    {
        if ($orgIds === []) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('o.organization_id', $orgIds)
            ->select('o.organization_id', \Illuminate\Support\Facades\DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
            ->get()->pluck('name', 'organization_id')->all();
    }
}
