<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FormTemplateHelper;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Form;
use App\Models\Request as ActionRequest;
use App\Services\DocumentGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectRequestController extends Controller
{
    public function index()
    {
        $form = Form::query()->where('route_name', 'project-request')->first();

        $requests = ActionRequest::query()
            ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->orderByDesc('requested_at')
            ->limit(300)
            ->get(['request_id', 'action_type', 'organization_id', 'requested_by', 'user', 'payload', 'requested_at']);

        if (! $form) {
            return view('pages.admin.project-requests.index', [
                'title' => 'Project Requests',
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
            return view('pages.admin.project-requests.index', [
                'title' => 'Project Requests',
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

        $rows = $requests->map(function ($request) use ($approvals, $requesterNames, $orgNames) {
            $payload = (array) ($request->payload ?? []);
            $organizationId = (int) ($request->organization_id ?? ($payload['organization_id'] ?? 0));
            $projectTitle = (string) ($payload['projectTitle'] ?? '');

            return [
                'request' => $request,
                'approval' => $approvals->get($request->request_id),
                'requester_name' => $requesterNames[$request->user] ?? 'Unknown',
                'org_name' => $orgNames[$organizationId] ?? 'Unknown Organization',
                'project_title' => $projectTitle,
                'submission_id' => (int) ($payload['submission_id'] ?? 0),
            ];
        });

        $pending = $rows->filter(fn ($row) => $row['approval'] === null)->values();
        $decided = $rows->filter(fn ($row) => $row['approval'] !== null)->take(30)->values();

        return view('pages.admin.project-requests.index', [
            'title' => 'Project Requests',
            'pending' => $pending,
            'decided' => $decided,
            'formMissing' => false,
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
            return back()->withErrors(['request' => 'This request is not a project request submission.']);
        }

        $form = Form::query()->where('route_name', 'project-request')->first();
        if (! $form) {
            return back()->withErrors(['form' => 'Project request form is not configured.']);
        }

        $payload = (array) ($actionRequest->payload ?? []);
        if ((int) ($payload['form_id'] ?? 0) !== (int) $form->getKey()) {
            return back()->withErrors(['request' => 'This request does not belong to the project request form.']);
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
            ? 'Project request approved successfully.'
            : 'Project request rejected.';

        return redirect()->route('admin.project-requests.index')->with('success', $message);
    }
}
