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

class JointStatementRequestController extends Controller
{
    public function index()
    {
        $form = Form::query()->where('route_name', 'joint-statement')->first();

        $requests = ActionRequest::query()
            ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->orderByDesc('requested_at')
            ->limit(300)
            ->get(['request_id', 'action_type', 'organization_id', 'requested_by', 'user', 'payload', 'requested_at']);

        if (! $form) {
            return view('pages.admin.joint-statement-requests.index', [
                'title' => 'Joint Statement Requests',
                'pending' => collect(),
                'decided' => collect(),
                'formMissing' => true,
            ]);
        }

        $formId = (int) $form->getKey();

        $requests = $requests
            ->filter(function ($request) use ($formId) {
                $payload = (array) ($request->payload ?? []);

                return (int) ($payload['form_id'] ?? 0) === $formId;
            })
            ->values();

        if ($requests->isEmpty()) {
            return view('pages.admin.joint-statement-requests.index', [
                'title' => 'Joint Statement Requests',
                'pending' => collect(),
                'decided' => collect(),
                'formMissing' => false,
            ]);
        }

        $requestIds = $requests->pluck('request_id')->all();

        $approvals = Approval::query()
            ->whereIn('request', $requestIds)
            ->get()
            ->keyBy('request');

        $requesterIds = $requests->pluck('user')->filter()->unique()->values()->all();
        $requesterNames = [];
        if (! empty($requesterIds)) {
            $requesterNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $requesterIds)
                ->select('u.user_id', DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
                ->get()
                ->pluck('name', 'user_id')
                ->all();
        }

        $orgIds = $requests
            ->map(function ($request) {
                $payload = (array) ($request->payload ?? []);
                $organizationId = (int) ($request->organization_id ?? 0);

                if ($organizationId > 0) {
                    return $organizationId;
                }

                return (int) ($payload['organization_id'] ?? 0);
            })
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $orgNames = [];
        if (! empty($orgIds)) {
            $orgNames = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $orgIds)
                ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                ->get()
                ->pluck('name', 'organization_id')
                ->all();
        }

        $generatedDocRequestIds = DB::table('generated_documents')
            ->whereIn('request_id', $requestIds)
            ->where('status', 'generated')
            ->pluck('request_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $rows = $requests->map(function ($request) use ($approvals, $requesterNames, $orgNames, $generatedDocRequestIds) {
            $payload = (array) ($request->payload ?? []);
            $organizationId = (int) ($request->organization_id ?? ($payload['organization_id'] ?? 0));
            $statementDate = (string) ($payload['date'] ?? '');

            return [
                'request' => $request,
                'approval' => $approvals->get($request->request_id),
                'requester_name' => $requesterNames[$request->user] ?? 'Unknown',
                'org_name' => $orgNames[$organizationId] ?? 'Unknown Organization',
                'statement_date' => $statementDate,
                'submission_id' => (int) ($payload['submission_id'] ?? 0),
                'has_document' => isset($generatedDocRequestIds[(int) $request->request_id]),
            ];
        });

        $pending = $rows->filter(fn ($row) => $row['approval'] === null)->values();
        $decided = $rows->filter(fn ($row) => $row['approval'] !== null)->take(30)->values();

        return view('pages.admin.joint-statement-requests.index', [
            'title' => 'Joint Statement Requests',
            'pending' => $pending,
            'decided' => $decided,
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

        $hasDocument = DB::table('generated_documents')
            ->where('request_id', $requestId)
            ->where('status', 'generated')
            ->exists();

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

        return view('pages.admin.joint-statement-requests.show', [
            'title' => 'Review Joint Statement',
            'actionRequest' => $actionRequest,
            'submission' => $submission,
            'submissionPayload' => $submission ? (array) ($submission->payload ?? []) : [],
            'approval' => $approval,
            'hasDocument' => $hasDocument,
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

        if ((int) $actionRequest->action_type !== FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION) {
            return back()->withErrors(['request' => 'This request is not a joint statement submission.']);
        }

        $form = Form::query()->where('route_name', 'joint-statement')->first();
        if (! $form) {
            return back()->withErrors(['form' => 'Joint statement form is not configured.']);
        }

        $payload = (array) ($actionRequest->payload ?? []);
        if ((int) ($payload['form_id'] ?? 0) !== (int) $form->getKey()) {
            return back()->withErrors(['request' => 'This request does not belong to the joint statement form.']);
        }

        $generatedDocumentId = null;
        $docGenWarning = null;

        if ($validated['decision'] === 'approve') {
            try {
                $generatedDocument = $documentGenerationService->generateAllFromApprovedRequest($actionRequest, $userId)->first();
                $generatedDocumentId = (int) $generatedDocument->getKey();
            } catch (\Throwable $throwable) {
                $docGenWarning = $throwable->getMessage();
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
            \App\Models\GeneratedDocument::where('request_id', $actionRequest->getKey())->where('status', 'generated')
                ->update(['approval_id' => (int) $approval->getKey()]);
        }

        if ($validated['decision'] === 'approve') {
            $message = $docGenWarning
                ? 'Joint statement approved. Document could not be generated: '.$docGenWarning
                : 'Joint statement approved successfully.';
        } else {
            $message = 'Joint statement rejected.';
        }

        return redirect()->route('admin.joint-statement-requests.index')->with('success', $message);
    }

    public function generate(Request $request, int $requestId, DocumentGenerationService $documentGenerationService): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $userId = (int) $user->getKey();

        $actionRequest = ActionRequest::query()->findOrFail($requestId);

        $approval = \App\Models\Approval::query()
            ->where('request', $requestId)
            ->where('is_rejected', false)
            ->first();

        if (! $approval) {
            return back()->withErrors(['request' => 'This request has not been approved yet.']);
        }

        $alreadyGenerated = DB::table('generated_documents')
            ->where('request_id', $requestId)
            ->where('status', 'generated')
            ->exists();

        if ($alreadyGenerated) {
            return redirect()->route('admin.joint-statement-requests.index')
                ->with('success', 'Document has already been generated for this request.');
        }

        try {
            $generatedDocument = $documentGenerationService->generateAllFromApprovedRequest($actionRequest, $userId)->first();

            \App\Models\GeneratedDocument::where('request_id', $actionRequest->getKey())->where('status', 'generated')
                ->update(['approval_id' => (int) $approval->getKey()]);
        } catch (\Throwable $throwable) {
            return back()->withErrors(['request' => 'Document generation failed: '.$throwable->getMessage()]);
        }

        return redirect()->route('admin.joint-statement-requests.index')
            ->with('success', 'Document generated successfully.');
    }
}
