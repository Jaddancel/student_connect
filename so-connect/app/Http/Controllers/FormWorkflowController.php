<?php

namespace App\Http\Controllers;

use App\Helpers\FormTemplateHelper;
use App\Models\Approval;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\Template;
use App\Models\Template\TemplateDescription;
use App\Services\DocumentGenerationService;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FormWorkflowController extends Controller
{
    public function manageForms(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isSuperAdmin = (int) $user->user_type === 1;

        if (! $isSuperAdmin) {
            abort(403);
        }

        $organizationQuery = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->select([
                'o.organization_id',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
            ])
            ->orderBy('organization_name');

        $formsQuery = Form::query()
            ->with([
                'requestType',
                'fields.mappings',
                'templates' => fn ($templateQuery) => $templateQuery->orderByDesc('version'),
            ])
            ->orderByDesc('updated_at')
            ->limit(150);

        $requestTypes = RequestType::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('pages.sidebar.manage-document-forms', [
            'title' => 'Manage Document Forms',
            'organizations' => $organizationQuery->get(),
            'forms' => $formsQuery->get(),
            'requestTypesByCategory' => $requestTypes->groupBy('category'),
            'isSuperAdmin' => $isSuperAdmin,
            'roleLevels' => Form::roleLevelOptions(),
        ]);
    }

    public function storeTemplate(Request $request, DocumentGenerationService $generationService): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $isSuperAdmin = (int) $user->user_type === 1;

        if (! $isSuperAdmin) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description_text' => ['nullable', 'string', 'max:2000'],
            'sidebar_group' => ['required', 'string', Rule::in(Form::SIDEBAR_GROUP_OPTIONS)],
            'request_type_id' => ['required', 'integer', Rule::exists('request_types', 'request_type_id')->where('is_active', true)],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'organization_id')],
            'template_file' => ['required', 'file', 'mimes:docx', 'max:10240'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $organizationId = array_key_exists('organization_id', $validated) && $validated['organization_id'] !== null
            ? (int) $validated['organization_id']
            : null;
        $userId = (int) $user->getKey();
        $requestType = RequestType::query()->findOrFail((int) $validated['request_type_id']);
        $sidebarGroup = (string) $validated['sidebar_group'];

        $uploadedFile = $request->file('template_file');

        if (! $uploadedFile || $uploadedFile->getRealPath() === false) {
            return back()->withErrors(['template_file' => 'Unable to read uploaded DOCX file.'])->withInput();
        }

        try {
            $placeholders = FormTemplateHelper::extractPlaceholdersFromDocx($uploadedFile->getRealPath());
        } catch (\Throwable $throwable) {
            return back()->withErrors(['template_file' => $throwable->getMessage()])->withInput();
        }

        if (empty($placeholders)) {
            return back()->withErrors([
                'template_file' => 'No placeholders were found. Use the {{field_name}} format inside your DOCX template.',
            ])->withInput();
        }

        $disk = (string) config('documents.disk', 'public');
        $templateDirectory = trim((string) config('documents.templates_directory', 'form-templates'), '/');
        $storedPath = $uploadedFile->store($templateDirectory.'/'.now()->format('Y/m/d'), $disk);

        if (! is_string($storedPath) || $storedPath === '') {
            return back()->withErrors(['template_file' => 'Unable to store uploaded DOCX file.'])->withInput();
        }

        $fieldDefinitions = $this->buildTemplateFieldDefinitions($placeholders);

        try {
            DB::transaction(function () use ($validated, $organizationId, $userId, $storedPath, $fieldDefinitions, $generationService, $sidebarGroup) {
                $form = Form::query()->create([
                    'name' => trim((string) $validated['name']),
                    'description_text' => trim((string) ($validated['description_text'] ?? '')) ?: null,
                    'request_type_id' => (int) $validated['request_type_id'],
                    'sidebar_group' => $sidebarGroup,
                    'organization_id' => $organizationId,
                    'created_by' => $userId,
                    'is_active' => true,
                    'is_published' => (bool) ($validated['is_published'] ?? false),
                ]);

                $template = Template::query()->create([
                    'form_id' => (int) $form->getKey(),
                    'organization_id' => $organizationId,
                    'uploaded_by' => $userId,
                    'template_name' => trim((string) $validated['name']).' Template',
                    'docx_path' => $storedPath,
                    'version' => 1,
                    'is_active' => true,
                ]);

                foreach ($fieldDefinitions as $fieldDefinition) {

                    $field = FormDescription::query()->create([
                        'form_id' => (int) $form->getKey(),
                        'field_key' => (string) $fieldDefinition['field_key'],
                        'field_label' => (string) $fieldDefinition['field_label'],
                        'field_type' => (string) $fieldDefinition['field_type'],
                        'is_required' => true,
                        'field_order' => (int) $fieldDefinition['field_order'],
                        'placeholder_hint' => (string) $fieldDefinition['placeholder_hint'],
                        'field_options' => null,
                    ]);

                    foreach ($fieldDefinition['placeholders'] as $placeholderKey) {
                        TemplateDescription::query()->create([
                            'template_id' => (int) $template->getKey(),
                            'form_description_id' => (int) $field->getKey(),
                            'placeholder_key' => (string) $placeholderKey,
                            'field_key' => (string) $fieldDefinition['field_key'],
                            'is_required' => true,
                        ]);
                    }
                }

                $generationService->createFormUploadRequest(
                    $organizationId,
                    (int) $form->getKey(),
                    (int) $template->getKey(),
                    $userId,
                );
            });
        } catch (\Throwable $throwable) {
            Storage::disk($disk)->delete($storedPath);

            return back()->withErrors([
                'template_file' => 'Failed to save form template: '.$throwable->getMessage(),
            ])->withInput();
        }

        return back()->with('success', 'Document form template uploaded and mapped successfully.');
    }

    public function showFormPage(Request $request, int $formId)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $userId = (int) $user->getKey();

        $form = $this->resolveAccessibleForm($request, $formId, $userId);

        if (! $form) {
            abort(404);
        }

        return view('pages.sidebar.form-request-page', [
            'title' => $form->name,
            'form' => $form,
            'requestRows' => $this->buildOwnDocumentRequestRows($userId, (int) $form->getKey()),
        ]);
    }

    public function submitFormPage(Request $request, int $formId, DocumentGenerationService $generationService): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $userId = (int) $user->getKey();
        $form = $this->resolveAccessibleForm($request, $formId, $userId);

        if (! $form) {
            abort(404);
        }

        $validated = $request->validate([
            'fields' => ['required', 'array'],
        ]);

        if ($form->templates->isEmpty()) {
            return back()->withErrors([
                'fields' => 'No active template is available for this form yet.',
            ])->withInput();
        }

        $rawFields = (array) ($validated['fields'] ?? []);
        $payload = [];
        $fieldErrors = [];

        foreach ($form->fields as $field) {
            $fieldKey = (string) $field->field_key;
            $normalizedValue = $this->normalizeRequestValue($rawFields[$fieldKey] ?? null);

            if ($field->field_type === 'multipleInputs') {
                $expectedCount = max(1, $field->mappings->count());
                $values = is_array($normalizedValue) ? array_values($normalizedValue) : [$normalizedValue];

                while (count($values) < $expectedCount) {
                    $values[] = '';
                }

                if ((bool) $field->is_required) {
                    for ($index = 0; $index < $expectedCount; $index++) {
                        if (trim((string) ($values[$index] ?? '')) === '') {
                            $fieldErrors['fields.'.$fieldKey.'.'.($index + 1)] = ($field->field_label ?: $fieldKey).' entry #'.($index + 1).' is required.';
                        }
                    }
                }

                $payload[$fieldKey] = $values;

                continue;
            }

            if ((bool) $field->is_required && $normalizedValue === '') {
                $fieldErrors['fields.'.$fieldKey] = ($field->field_label ?: $fieldKey).' is required.';

                continue;
            }

            $payload[$fieldKey] = $normalizedValue;
        }

        if (! empty($fieldErrors)) {
            return back()->withErrors($fieldErrors)->withInput();
        }

        $submission = FormSubmission::query()->create([
            'form_id' => (int) $form->getKey(),
            'organization_id' => $form->organization_id !== null ? (int) $form->organization_id : null,
            'submitted_by' => $userId,
            'payload' => $payload,
            'submitted_at' => now(),
        ]);

        $actionRequest = $generationService->createDocumentGenerationRequest(
            $form->organization_id !== null ? (int) $form->organization_id : null,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        return redirect()
            ->route('forms.show', ['formId' => (int) $form->getKey()])
            ->with('success', 'Document generation request #'.(int) $actionRequest->getKey().' submitted.');
    }

    private function resolveAccessibleForm(Request $request, int $formId, int $userId): ?Form
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);

        if (empty($presidentOrganizationIds)) {
            abort(403);
        }

        $form = Form::query()
            ->with([
                'fields.mappings',
                'templates' => fn ($templateQuery) => $templateQuery->where('is_active', true)->orderByDesc('version'),
            ])
            ->whereKey($formId)
            ->where('is_active', true)
            ->where('is_published', true)
            ->where(function ($query) use ($presidentOrganizationIds) {
                $query->whereNull('organization_id')
                    ->orWhereIn('organization_id', $presidentOrganizationIds);
            })
            ->first();

        return $form;
    }

    public function generatedDocuments(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $userId = (int) $user->getKey();
        $officerOrganizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);

        $rows = GeneratedDocument::query()
            ->with(['submission.form', 'request'])
            ->where(function ($query) use ($userId, $officerOrganizationIds, $presidentOrganizationIds) {
                $query->whereHas('submission', function ($submissionQuery) use ($userId) {
                    $submissionQuery->where('submitted_by', $userId);
                });

                if (! empty($officerOrganizationIds)) {
                    $query->orWhereHas('submission', function ($submissionQuery) use ($officerOrganizationIds) {
                        $submissionQuery->whereIn('organization_id', $officerOrganizationIds);
                    });
                }

                if (! empty($presidentOrganizationIds)) {
                    $query->orWhereHas('submission', function ($submissionQuery) use ($presidentOrganizationIds) {
                        $submissionQuery->whereIn('organization_id', $presidentOrganizationIds);
                    });
                }
            })
            ->orderByDesc('generated_document_id')
            ->limit(150)
            ->get();

        return view('pages.sidebar.generated-documents', [
            'title' => 'Generated Documents',
            'rows' => $rows,
        ]);
    }

    public function downloadGenerated(Request $request, int $generatedDocumentId, DocumentGenerationService $generationService): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $generatedDocument = GeneratedDocument::query()->with('submission')->findOrFail($generatedDocumentId);
        $submission = $generatedDocument->submission;

        if (! $submission) {
            return back()->with('status', 'The associated form submission no longer exists.');
        }

        $userId = (int) $user->getKey();
        $organizationId = (int) ($submission->organization_id ?? 0);
        $submitterId = (int) ($submission->submitted_by ?? 0);
        $officerOrganizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);

        if (! in_array($organizationId, $officerOrganizationIds, true) && $submitterId !== $userId) {
            $action = FormTemplateHelper::encodeDocumentAccessAction($organizationId, (int) $generatedDocument->getKey(), $userId);

            $hasApprovedAccess = ActionRequest::query()
                ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_ACCESS)
                ->where('action', $action)
                ->whereIn('request_id', Approval::query()->select('request')->where('is_rejected', false))
                ->exists();

            if (! $hasApprovedAccess) {
                $hasPendingAccessRequest = ActionRequest::query()
                    ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_ACCESS)
                    ->where('action', $action)
                    ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
                    ->exists();

                if (! $hasPendingAccessRequest) {
                    $generationService->createDocumentAccessRequest($organizationId, (int) $generatedDocument->getKey(), $userId);
                }

                return back()->with('status', 'Access request submitted to your president.');
            }
        }

        if ($generatedDocument->status !== 'generated' || trim((string) ($generatedDocument->pdf_path ?? '')) === '') {
            return back()->with('status', 'This document is not ready for download yet.');
        }

        $disk = (string) config('documents.disk', 'public');
        $pdfPath = (string) $generatedDocument->pdf_path;

        if (! Storage::disk($disk)->exists($pdfPath)) {
            return back()->with('status', 'Generated PDF file could not be found in storage.');
        }

        return response()->download(Storage::disk($disk)->path($pdfPath));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildOwnDocumentRequestRows(int $userId, ?int $formId = null): Collection
    {
        $requests = ActionRequest::query()
            ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->where('user', $userId)
            ->orderByDesc('requested_at')
            ->limit(100)
            ->get(['request_id', 'action', 'requested_at']);

        if ($formId !== null) {
            $requests = $requests->filter(function (ActionRequest $actionRequest) use ($formId) {
                [, , $decodedFormId] = FormTemplateHelper::decodeDocumentGenerationAction($actionRequest->action);

                return (int) $decodedFormId === $formId;
            })->values();
        }

        if ($requests->isEmpty()) {
            return collect();
        }

        $requestIds = $requests->pluck('request_id')->map(fn ($id) => (int) $id)->values();
        $approvalMap = Approval::query()->whereIn('request', $requestIds->all())->get(['request', 'is_rejected'])->keyBy('request');
        $generatedMap = GeneratedDocument::query()->whereIn('request_id', $requestIds->all())->get()->keyBy('request_id');

        $formIds = $requests
            ->map(function (ActionRequest $actionRequest) {
                [, , $formId] = FormTemplateHelper::decodeDocumentGenerationAction($actionRequest->action);

                return $formId;
            })
            ->filter(fn (int $formId) => $formId > 0)
            ->unique()
            ->values();

        $formNameMap = Form::query()->whereIn('id', $formIds->all())->pluck('name', 'id')->all();

        return $requests->map(function (ActionRequest $actionRequest) use ($approvalMap, $generatedMap, $formNameMap) {
            [, $submissionId, $formId] = FormTemplateHelper::decodeDocumentGenerationAction($actionRequest->action);

            $approval = $approvalMap->get((int) $actionRequest->request_id);
            $status = ! $approval ? 'pending' : ((bool) $approval->is_rejected ? 'rejected' : 'approved');
            $generated = $generatedMap->get((int) $actionRequest->request_id);

            return [
                'request_id' => (int) $actionRequest->request_id,
                'form_name' => (string) ($formNameMap[$formId] ?? ('Form #'.$formId)),
                'submission_id' => $submissionId,
                'requested_at' => $actionRequest->requested_at,
                'status' => $status,
                'status_label' => ucfirst($status),
                'generated_document_id' => (int) ($generated->generated_document_id ?? 0),
                'generated_status' => (string) ($generated->status ?? ''),
                'has_pdf' => trim((string) ($generated->pdf_path ?? '')) !== '',
            ];
        })->values();
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeRequestValue($value): array|string
    {
        if (is_array($value)) {
            return array_values(array_map(
                fn ($item) => trim((string) $item),
                $value,
            ));
        }

        return trim((string) $value);
    }

    /**
     * @param  array<int, string>  $placeholders
     * @return array<int, array{field_key:string,field_label:string,field_type:string,field_order:int,placeholder_hint:string,placeholders:array<int, string>}>
     */
    private function buildTemplateFieldDefinitions(array $placeholders): array
    {
        $definitions = [];

        foreach ($placeholders as $order => $placeholder) {
            $normalized = FormTemplateHelper::normalizeFieldKey((string) $placeholder);
            $baseKey = FormTemplateHelper::placeholderBaseKey($normalized);
            $placeholderIndex = FormTemplateHelper::placeholderIndex($normalized);

            if ($placeholderIndex !== null) {
                if (! isset($definitions[$baseKey])) {
                    $definitions[$baseKey] = [
                        'field_key' => $baseKey,
                        'field_label' => (string) Str::of($baseKey)->replace('_', ' ')->title(),
                        'field_type' => 'multipleInputs',
                        'field_order' => $order,
                        'placeholder_hint' => FormTemplateHelper::wrapIndexedPlaceholder($baseKey),
                        'placeholders' => [],
                    ];
                }

                $definitions[$baseKey]['field_type'] = 'multipleInputs';
                $definitions[$baseKey]['placeholder_hint'] = FormTemplateHelper::wrapIndexedPlaceholder($baseKey);
                $definitions[$baseKey]['placeholders'][$placeholderIndex] = $normalized;

                continue;
            }

            if (! isset($definitions[$normalized])) {
                $definitions[$normalized] = [
                    'field_key' => $normalized,
                    'field_label' => (string) Str::of($normalized)->replace('_', ' ')->title(),
                    'field_type' => 'text',
                    'field_order' => $order,
                    'placeholder_hint' => FormTemplateHelper::wrapPlaceholder($normalized),
                    'placeholders' => [$normalized],
                ];
            }
        }

        foreach ($definitions as &$definition) {
            if (($definition['field_type'] ?? 'text') === 'multipleInputs') {
                ksort($definition['placeholders']);
            }
        }

        unset($definition);

        uasort($definitions, function (array $left, array $right): int {
            $comparison = ($left['field_order'] ?? 0) <=> ($right['field_order'] ?? 0);

            if ($comparison !== 0) {
                return $comparison;
            }

            return strcmp($left['field_key'], $right['field_key']);
        });

        return array_values($definitions);
    }
}
