<?php

namespace App\Http\Controllers;

use App\Helpers\FormTemplateHelper;
use App\Models\Approval;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
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

        $userId = (int) $user->getKey();
        $isSuperAdmin = (int) $user->user_type === 1;
        $presidentOrganizationIds = $isSuperAdmin
            ? []
            : OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);

        if (! $isSuperAdmin && empty($presidentOrganizationIds)) {
            abort(403);
        }

        $organizationQuery = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->select([
                'o.organization_id',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
            ])
            ->orderBy('organization_name');

        if (! $isSuperAdmin) {
            $organizationQuery->whereIn('o.organization_id', $presidentOrganizationIds);
        }

        $formsQuery = Form::query()
            ->with([
                'fields',
                'templates' => fn ($templateQuery) => $templateQuery->orderByDesc('version'),
            ])
            ->orderByDesc('updated_at')
            ->limit(150);

        if (! $isSuperAdmin) {
            $formsQuery->whereIn('organization_id', $presidentOrganizationIds);
        }

        return view('pages.sidebar.manage-document-forms', [
            'title' => 'Manage Document Forms',
            'organizations' => $organizationQuery->get(),
            'forms' => $formsQuery->get(),
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    public function storeTemplate(Request $request, DocumentGenerationService $generationService): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description_text' => ['nullable', 'string', 'max:2000'],
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'organization_id')],
            'template_file' => ['required', 'file', 'mimes:docx', 'max:10240'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $organizationId = (int) $validated['organization_id'];
        $userId = (int) $user->getKey();

        if ((int) $user->user_type !== 1) {
            $authorizedOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);

            if (! in_array($organizationId, $authorizedOrganizationIds, true)) {
                abort(403);
            }
        }

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

        try {
            DB::transaction(function () use ($validated, $organizationId, $userId, $storedPath, $placeholders, $generationService) {
                $form = Form::query()->create([
                    'name' => trim((string) $validated['name']),
                    'description_text' => trim((string) ($validated['description_text'] ?? '')) ?: null,
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

                foreach ($placeholders as $index => $placeholder) {
                    $fieldKey = FormTemplateHelper::normalizeFieldKey($placeholder);

                    $field = FormDescription::query()->create([
                        'form_id' => (int) $form->getKey(),
                        'field_key' => $fieldKey,
                        'field_label' => (string) Str::of($fieldKey)->replace('_', ' ')->title(),
                        'field_type' => 'text',
                        'is_required' => true,
                        'field_order' => $index,
                        'placeholder_hint' => FormTemplateHelper::wrapPlaceholder($fieldKey),
                        'field_options' => null,
                    ]);

                    TemplateDescription::query()->create([
                        'template_id' => (int) $template->getKey(),
                        'form_description_id' => (int) $field->getKey(),
                        'placeholder_key' => $fieldKey,
                        'field_key' => $fieldKey,
                        'is_required' => true,
                    ]);
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

    public function requestGenerationPage(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $userId = (int) $user->getKey();
        $officerOrganizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);

        $forms = Form::query()
            ->with([
                'fields',
                'templates' => fn ($templateQuery) => $templateQuery->where('is_active', true)->orderByDesc('version'),
            ])
            ->where('is_active', true)
            ->where('is_published', true)
            ->when(! empty($officerOrganizationIds), function ($query) use ($officerOrganizationIds) {
                $query->whereIn('organization_id', $officerOrganizationIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->orderBy('name')
            ->get();

        $selectedFormId = (int) ($request->query('form_id') ?? old('form_id') ?? ($forms->first()->id ?? 0));

        return view('pages.sidebar.document-generation-request', [
            'title' => 'Document Generation Requests',
            'forms' => $forms,
            'selectedFormId' => $selectedFormId,
            'selectedForm' => $forms->firstWhere('id', $selectedFormId),
            'requestRows' => $this->buildOwnDocumentRequestRows($userId),
        ]);
    }

    public function storeGenerationRequest(Request $request, DocumentGenerationService $generationService): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'form_id' => ['required', 'integer', Rule::exists('forms', 'id')],
            'fields' => ['required', 'array'],
        ]);

        $form = Form::query()
            ->with(['fields', 'templates' => fn ($query) => $query->where('is_active', true)->orderByDesc('version')])
            ->findOrFail((int) $validated['form_id']);

        if (! (bool) $form->is_active || ! (bool) $form->is_published) {
            return back()->withErrors([
                'form_id' => 'This form is not currently available for requests.',
            ])->withInput();
        }

        $userId = (int) $user->getKey();
        $officerOrganizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);

        if (! in_array((int) $form->organization_id, $officerOrganizationIds, true)) {
            abort(403);
        }

        if ($form->templates->isEmpty()) {
            return back()->withErrors([
                'form_id' => 'No active template is available for this form yet.',
            ])->withInput();
        }

        $rawFields = (array) ($validated['fields'] ?? []);
        $payload = [];
        $fieldErrors = [];

        foreach ($form->fields as $field) {
            $fieldKey = (string) $field->field_key;
            $normalizedValue = $this->normalizeRequestValue($rawFields[$fieldKey] ?? null);

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
            'organization_id' => (int) ($form->organization_id ?? 0) ?: null,
            'submitted_by' => $userId,
            'payload' => $payload,
            'submitted_at' => now(),
        ]);

        $actionRequest = $generationService->createDocumentGenerationRequest(
            (int) ($form->organization_id ?? 0),
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        return redirect()
            ->route('forms.request-generation', ['form_id' => (int) $form->getKey()])
            ->with('success', 'Document generation request #'.(int) $actionRequest->getKey().' submitted.');
    }

    public function generatedDocuments(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $userId = (int) $user->getKey();
        $officerOrganizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);

        $rows = GeneratedDocument::query()
            ->with(['submission.form', 'request'])
            ->where(function ($query) use ($userId, $officerOrganizationIds) {
                $query->whereHas('submission', function ($submissionQuery) use ($userId) {
                    $submissionQuery->where('submitted_by', $userId);
                });

                if (! empty($officerOrganizationIds)) {
                    $query->orWhereHas('submission', function ($submissionQuery) use ($officerOrganizationIds) {
                        $submissionQuery->whereIn('organization_id', $officerOrganizationIds);
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
    private function buildOwnDocumentRequestRows(int $userId): Collection
    {
        $requests = ActionRequest::query()
            ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->where('user', $userId)
            ->orderByDesc('requested_at')
            ->limit(100)
            ->get(['request_id', 'action', 'requested_at']);

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
    private function normalizeRequestValue($value): string
    {
        if (is_array($value)) {
            return trim(implode(', ', array_map(fn ($item) => trim((string) $item), $value)));
        }

        return trim((string) $value);
    }
}
