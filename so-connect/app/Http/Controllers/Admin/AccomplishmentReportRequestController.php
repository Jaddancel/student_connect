<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FormTemplateHelper;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Services\DocumentGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccomplishmentReportRequestController extends Controller
{
    private const FORM_ROUTE = 'accomplishment-report';
    private const PAGE_TITLE = 'Accomplishment Report Requests';

    public function index()
    {
        $form = Form::query()->where('route_name', self::FORM_ROUTE)->first();

        if (! $form) {
            return view('pages.admin.accomplishment-report-requests.index', [
                'title' => self::PAGE_TITLE,
                'pending' => collect(),
                'decided' => collect(),
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
            return view('pages.admin.accomplishment-report-requests.index', [
                'title' => self::PAGE_TITLE,
                'pending' => collect(),
                'decided' => collect(),
                'formMissing' => false,
            ]);
        }

        $requestIds = $requests->pluck('request_id')->all();
        $approvals = Approval::query()->whereIn('request', $requestIds)->get()->keyBy('request');

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

        $rows = $requests->map(function ($r) use ($approvals, $requesterNames, $orgNames) {
            $payload = (array) ($r->payload ?? []);
            $orgId = (int) ($r->organization_id ?? ($payload['organization_id'] ?? 0));
            return [
                'request' => $r,
                'approval' => $approvals->get($r->request_id),
                'requester_name' => $requesterNames[$r->user] ?? 'Unknown',
                'org_name' => $orgNames[$orgId] ?? 'Unknown Organization',
                'submission_id' => (int) ($payload['submission_id'] ?? 0),
            ];
        });

        return view('pages.admin.accomplishment-report-requests.index', [
            'title' => self::PAGE_TITLE,
            'pending' => $rows->filter(fn ($row) => $row['approval'] === null)->values(),
            'decided' => $rows->filter(fn ($row) => $row['approval'] !== null)->take(30)->values(),
            'formMissing' => false,
        ]);
    }

    public function show(int $requestId)
    {
        $actionRequest = ActionRequest::query()->findOrFail($requestId);
        $payload = (array) ($actionRequest->payload ?? []);
        $submissionId = (int) ($payload['submission_id'] ?? 0);
        $submission = $submissionId ? FormSubmission::query()->find($submissionId) : null;

        $approval = Approval::query()->where('request', $requestId)->first();

        $orgId = (int) ($actionRequest->organization_id ?? ($payload['organization_id'] ?? 0));
        $orgName = 'Unknown Organization';
        if ($orgId > 0) {
            $orgRow = DB::table('organizations as o')
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

        return view('pages.admin.accomplishment-report-requests.show', [
            'title' => self::PAGE_TITLE,
            'actionRequest' => $actionRequest,
            'submission' => $submission,
            'submissionPayload' => $submission ? (array) ($submission->payload ?? []) : [],
            'approval' => $approval,
            'orgName' => $orgName,
            'requesterName' => $requesterName,
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
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $actionRequest = ActionRequest::query()->findOrFail($requestId);

        $form = Form::query()->where('route_name', self::FORM_ROUTE)->first();
        if (! $form) {
            return back()->withErrors(['form' => 'Form is not configured.']);
        }

        $payload = (array) ($actionRequest->payload ?? []);
        if ((int) ($payload['form_id'] ?? 0) !== (int) $form->getKey()) {
            return back()->withErrors(['request' => 'This request does not belong to the accomplishment report form.']);
        }

        $generatedDocumentId = null;
        if ($validated['decision'] === 'approve') {
            try {
                $generatedDocument = $documentGenerationService->generateFromApprovedRequest($actionRequest, $userId);
                $generatedDocumentId = (int) $generatedDocument->getKey();
            } catch (\Throwable $throwable) {
                return back()->withErrors(['request' => $throwable->getMessage()]);
            }
        }

        $approval = Approval::query()->updateOrCreate(
            ['request' => $requestId],
            [
                'admin' => $userId,
                'approved_at' => now(),
                'is_rejected' => $validated['decision'] === 'reject',
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]
        );

        if ($generatedDocumentId) {
            \App\Models\GeneratedDocument::where('generated_document_id', $generatedDocumentId)
                ->update(['approval_id' => (int) $approval->approval_id]);
        }

        $message = $validated['decision'] === 'approve'
            ? 'Accomplishment report approved and document generated.'
            : 'Accomplishment report request rejected.';

        return redirect()->route('admin.accomplishment-report-requests.index')->with('success', $message);
    }
}
