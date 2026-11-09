<?php

namespace App\Services;

use App\Forms\SystemFunction;
use App\Helpers\FormTemplateHelper;
use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
use App\Models\Workplan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnifiedRequestDecisionService
{
    public function __construct(
        private readonly DocumentGenerationService $documents,
        private readonly WorkplanService $workplans,
    ) {}

    /**
     * @return array<string,array{label:string,short_label:string,route_name:?string,show_route:string,index_route:string}>
     */
    public static function workflows(): array
    {
        return [
            'projects' => ['label' => 'Project Requests', 'short_label' => 'Projects', 'route_name' => 'project-request', 'show_route' => 'admin.project-requests.show', 'index_route' => 'admin.project-requests.index'],
            'joint-statements' => ['label' => 'Joint Statement Requests', 'short_label' => 'Joint Statements', 'route_name' => 'joint-statement', 'show_route' => 'admin.joint-statement-requests.show', 'index_route' => 'admin.joint-statement-requests.index'],
            'accomplishment-reports' => ['label' => 'Accomplishment Reports', 'short_label' => 'Accomplishments', 'route_name' => 'accomplishment-report', 'show_route' => 'admin.accomplishment-report-requests.show', 'index_route' => 'admin.accomplishment-report-requests.index'],
            'financial-reports' => ['label' => 'Financial Reports', 'short_label' => 'Financial', 'route_name' => 'financial-report', 'show_route' => 'admin.financial-report-requests.show', 'index_route' => 'admin.financial-report-requests.index'],
            'recognition' => ['label' => 'Recognition Requests', 'short_label' => 'Recognition', 'route_name' => 'organization-recognition', 'show_route' => 'admin.recognition-requests.show', 'index_route' => 'admin.recognition-requests.index'],
            'workplans' => ['label' => 'Workplans', 'short_label' => 'Workplans', 'route_name' => null, 'show_route' => 'admin.workplan-requests.show', 'index_route' => 'admin.workplan-requests.index'],
        ];
    }

    /** @return array<string,Form> */
    public function configuredForms(): array
    {
        $routes = collect(self::workflows())->pluck('route_name')->filter()->values();
        $formsByRoute = Form::query()->whereIn('route_name', $routes)->get()->keyBy('route_name');
        $forms = [];

        foreach (self::workflows() as $key => $workflow) {
            $form = $key === 'workplans'
                ? SystemFunction::form(SystemFunction::NEW_WORKPLAN)
                : $formsByRoute->get($workflow['route_name']);

            if ($form) {
                $forms[$key] = $form;
            }
        }

        return $forms;
    }

    public function workflowFor(ActionRequest $request): ?string
    {
        $formId = (int) ($request->form_id ?: (($request->payload ?? [])['form_id'] ?? 0));

        foreach ($this->configuredForms() as $key => $form) {
            if ((int) $form->getKey() === $formId) {
                return $key;
            }
        }

        return null;
    }

    /** @return array{warning:?string} */
    public function decide(ActionRequest $request, int $adminId, string $decision, ?string $reason = null): array
    {
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new RuntimeException('The requested decision is invalid.');
        }

        $reason = is_string($reason) ? trim($reason) : null;
        if ($decision === 'reject' && ($reason === null || $reason === '')) {
            throw new RuntimeException('A rejection reason is required.');
        }

        $result = DB::transaction(function () use ($request, $adminId, $decision, $reason) {
            // Serialize decisions for one request. This prevents two admins (or a
            // double submit) from generating duplicate documents and approvals.
            $lockedRequest = ActionRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedRequest) {
                throw new RuntimeException('The request was not found.');
            }

            if ((int) $lockedRequest->action_type !== FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION) {
                throw new RuntimeException('This item is not a document-generation request.');
            }

            $workflow = $this->workflowFor($lockedRequest);
            if ($workflow === null) {
                throw new RuntimeException('This request is not part of the unified approvals center.');
            }

            if (Approval::query()->where('request', $lockedRequest->getKey())->exists()) {
                throw new RuntimeException('This request has already been decided.');
            }

            $warning = null;
            $generatedDocumentId = null;

            if ($decision === 'approve') {
                try {
                    $generatedDocument = $this->documents
                        ->generateAllFromApprovedRequest($lockedRequest, $adminId)
                        ->first();
                    $generatedDocumentId = $generatedDocument
                        ? (int) $generatedDocument->getKey()
                        : 0;

                    if ($generatedDocumentId <= 0) {
                        throw new RuntimeException('No document was generated.');
                    }
                } catch (\Throwable $throwable) {
                    // Joint statements historically allow approval before a template
                    // is ready and expose a separate Generate action on the detail page.
                    if ($workflow !== 'joint-statements') {
                        // Do not throw until after this transaction commits. The
                        // document service deliberately persists a failed-generation
                        // record for diagnostics; throwing here would roll it back.
                        return [
                            'request' => $lockedRequest,
                            'workflow' => $workflow,
                            'warning' => null,
                            'failure' => $throwable,
                        ];
                    }

                    $warning = 'Document generation is still pending: '.$throwable->getMessage();
                }
            }

            $approval = Approval::query()->create([
                'request' => (int) $lockedRequest->getKey(),
                'admin' => $adminId,
                'approved_at' => now(),
                'is_rejected' => $decision === 'reject',
                'rejection_reason' => $decision === 'reject' ? $reason : null,
            ]);

            if ($generatedDocumentId !== null) {
                GeneratedDocument::query()
                    ->where('request_id', $lockedRequest->getKey())
                    ->where('status', 'generated')
                    ->update(['approval_id' => (int) $approval->getKey()]);
            }

            return [
                'request' => $lockedRequest,
                'workflow' => $workflow,
                'warning' => $warning,
                'failure' => null,
            ];
        }, 3);

        if ($result['failure'] instanceof \Throwable) {
            throw $result['failure'];
        }

        if ($decision === 'approve' && $result['workflow'] === 'workplans') {
            $workplan = $this->resolveWorkplan($result['request']);

            if (! $workplan) {
                $result['warning'] = 'Workplan approved, but no matching workplan was found for calendar synchronization.';
            } else {
                try {
                    $this->workplans->convertApprovedPlansToCalendarEvents($workplan);
                } catch (\Throwable $throwable) {
                    $result['warning'] = 'Workplan approved, but calendar synchronization needs attention: '.$throwable->getMessage();
                }
            }
        }

        return ['warning' => $result['warning']];
    }

    private function resolveWorkplan(ActionRequest $request): ?Workplan
    {
        $payload = (array) ($request->payload ?? []);
        $submission = ! empty($payload['submission_id'])
            ? FormSubmission::query()->find((int) $payload['submission_id'])
            : null;
        $submissionPayload = (array) ($submission?->payload ?? []);
        $organizationId = (int) ($request->organization_id ?: ($payload['organization_id'] ?? $submission?->organization_id ?? 0));
        $semesterId = (int) ($payload['semester_id'] ?? $submissionPayload['semester_id'] ?? 0);

        if ($organizationId <= 0) {
            return null;
        }

        $query = Workplan::query()->where('organization_id', $organizationId);

        return $semesterId > 0
            ? $query->where('semester_id', $semesterId)->first()
            : $query->orderByDesc('semester_id')->first();
    }
}
