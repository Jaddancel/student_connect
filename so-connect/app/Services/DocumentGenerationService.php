<?php

namespace App\Services;

use App\Mail\DocumentGeneratedMail;
use App\Helpers\FormTemplateHelper;
use App\Models\Approval;
use App\Models\Document;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workplan;
use App\Support\OrganizationField;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Generates the printable PDF for a form submission by rendering the form's
 * WYSIWYG layout (or, as a fallback, its ordered fields) to HTML and converting
 * it with dompdf.
 *
 * This replaces the previous DOCX-template pipeline (phpword + LibreOffice).
 * The request/approval plumbing (action encode/decode via {@see FormTemplateHelper})
 * is unchanged, so existing approval workflows keep working.
 */
class DocumentGenerationService
{
    public function createDocumentGenerationRequest(
        ?int $organizationId,
        int $submissionId,
        int $formId,
        int $requesterUserId,
    ): ActionRequest {
        // Requests are typed after the originating form when it has its own
        // request type (every unbound builder form does); the generic
        // form_generation system type remains the fallback. The action_type
        // discriminator is unchanged either way, so approval dispatch,
        // badges and scoring joins are unaffected.
        $formRequestTypeId = $formId
            ? Form::query()->whereKey($formId)->value('request_type_id')
            : null;

        $requestTypeId = $formRequestTypeId
            ? (int) $formRequestTypeId
            : (int) app(\App\Services\RequestTypeService::class)->resolveSystemType(
                RequestType::SYSTEM_KEY_FORM_GENERATION,
                'Form Generation Request',
                RequestType::CATEGORY_ORGANIZATION,
                $requesterUserId,
            )->getKey();

        $actionRequest = ActionRequest::query()->create([
            'action' => FormTemplateHelper::encodeDocumentGenerationAction(
                (int) ($organizationId ?? 0),
                $submissionId,
                $formId,
                $requesterUserId,
            ),
            'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
            'request_type_id' => $requestTypeId,
            'form_id' => $formId ?: null,
            'organization_id' => $organizationId ?: null,
            'requested_by' => $requesterUserId,
            'payload' => [
                'organization_id' => $organizationId ?: null,
                'submission_id' => $submissionId,
                'form_id' => $formId,
                'requester_user_id' => $requesterUserId,
            ],
            'user' => $requesterUserId,
            'requested_at' => now(),
        ]);

        return $actionRequest;
    }

    public function createDocumentAccessRequest(?int $organizationId, int $generatedDocumentId, int $requesterUserId): ActionRequest
    {
        $requestType = app(\App\Services\RequestTypeService::class)->resolveSystemType(
            RequestType::SYSTEM_KEY_FORM_ACCESS,
            'Document Access Request',
            RequestType::CATEGORY_ORGANIZATION,
            $requesterUserId,
        );

        $actionRequest = ActionRequest::query()->create([
            'action' => FormTemplateHelper::encodeDocumentAccessAction((int) ($organizationId ?? 0), $generatedDocumentId, $requesterUserId),
            'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_ACCESS,
            'request_type_id' => (int) $requestType->getKey(),
            'organization_id' => $organizationId ?: null,
            'requested_by' => $requesterUserId,
            'payload' => [
                'organization_id' => $organizationId ?: null,
                'generated_document_id' => $generatedDocumentId,
                'requester_user_id' => $requesterUserId,
            ],
            'user' => $requesterUserId,
            'requested_at' => now(),
        ]);

        return $actionRequest;
    }

    public function createFormUploadRequest(?int $organizationId, int $formId, int $templateId, int $uploaderUserId): ActionRequest
    {
        $requestType = app(\App\Services\RequestTypeService::class)->resolveSystemType(
            RequestType::SYSTEM_KEY_FORM_UPLOAD,
            'Form Upload Request',
            RequestType::CATEGORY_ORGANIZATION,
            $uploaderUserId,
        );

        $actionRequest = ActionRequest::query()->create([
            'action' => FormTemplateHelper::encodeFormUploadAction((int) ($organizationId ?? 0), $formId, $templateId, $uploaderUserId),
            'action_type' => FormTemplateHelper::ACTION_TYPE_FORM_UPLOAD,
            'request_type_id' => (int) $requestType->getKey(),
            'organization_id' => $organizationId ?: null,
            'requested_by' => $uploaderUserId,
            'payload' => [
                'organization_id' => $organizationId ?: null,
                'form_id' => $formId,
                'template_id' => $templateId,
                'uploader_user_id' => $uploaderUserId,
            ],
            'user' => $uploaderUserId,
            'requested_at' => now(),
        ]);

        return $actionRequest;
    }

    public function generateFromApprovedRequest(ActionRequest $request, int $generatedByUserId): GeneratedDocument
    {
        [$organizationId, $submissionId, $formId] = FormTemplateHelper::decodeDocumentGenerationAction($request->action);

        // organizationId == 0 means the form is not scoped to a specific org — that is valid.
        if ($submissionId <= 0 || $formId <= 0) {
            throw new RuntimeException('Document generation request payload is invalid.');
        }

        $submission = FormSubmission::query()->find($submissionId);

        if (! $submission) {
            throw new RuntimeException('Requested form submission was not found.');
        }

        if ((int) $submission->form_id !== $formId) {
            throw new RuntimeException('Form submission and requested form do not match.');
        }

        $submissionOrgId = (int) ($submission->organization_id ?? 0);
        if ($submissionOrgId !== $organizationId) {
            throw new RuntimeException('Form submission organization does not match request organization.');
        }

        return $this->generateFromSubmission(
            $submission,
            (int) $request->getKey(),
            $generatedByUserId,
        );
    }

    /**
     * Render a submission's form layout (or ordered fields) to a PDF and persist it.
     */
    public function generateFromSubmission(
        FormSubmission $submission,
        ?int $requestId,
        int $generatedByUserId,
    ): GeneratedDocument {
        $disk = (string) config('documents.disk', 'public');

        $generatedPdfRelativePath = $this->nextGeneratedPdfPath((int) $submission->getKey());
        $generatedPdfAbsolutePath = Storage::disk($disk)->path($generatedPdfRelativePath);
        File::ensureDirectoryExists(dirname($generatedPdfAbsolutePath));

        try {
            $form = $submission->form ?: Form::query()->find($submission->form_id);
            if (! $form) {
                throw new RuntimeException('Form for this submission was not found.');
            }

            $fields = FormDescription::query()
                ->where('form_id', (int) $submission->form_id)
                ->orderBy('field_order')
                ->get();

            // The printed document is driven by the form's separate PDF template
            // (rich-text HTML with field tokens), not the online-form layout.
            $pdfTemplate = (array) ($form->pdf_template ?? []);
            $templateHtml = (string) ($pdfTemplate['html'] ?? '');
            if (trim($templateHtml) === '') {
                throw new RuntimeException('This form has no printed template; a document cannot be generated.');
            }

            $page = (array) ($pdfTemplate['page'] ?? []);
            $size = (string) ($page['size'] ?? 'a4');
            $size = in_array($size, ['a4', 'letter', 'legal'], true) ? $size : 'a4';
            $orientation = (string) ($page['orientation'] ?? 'portrait');
            $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';

            // Resolve the submitter's profile + organization so `data-universal`
            // tokens print from them. `profile` is a FK column that shadows the
            // relation, so the related model must be loaded explicitly.
            $submitter = $submission->submitted_by
                ? User::find((int) $submission->submitted_by)
                : null;
            $submitterProfile = $submitter?->profile()->first();
            $submitterOrganization = OrganizationField::resolveOrganization($submitter);

            $body = app(\App\Forms\PdfTemplateRenderer::class)->render(
                $templateHtml,
                (array) $submission->payload,
                $fields,
                $disk,
                $submitterProfile,
                $submitterOrganization,
            );

            $html = view('documents.form-template-pdf', [
                'form' => $form,
                'body' => $body,
                'page' => $page,
                'font' => (array) ($pdfTemplate['font'] ?? []),
                'header' => (array) ($pdfTemplate['header'] ?? []),
                'footer' => (array) ($pdfTemplate['footer'] ?? []),
            ])->render();

            $pdf = Pdf::loadHTML($html)->setPaper($size, $orientation);
            Storage::disk($disk)->put($generatedPdfRelativePath, $pdf->output());

            // Recognition forms attach the org's approved workplan PDF, if any.
            $workplanPdfAbsPath = $this->findWorkplanApprovedPdf((array) $submission->payload, $disk);
            if ($workplanPdfAbsPath !== null) {
                $this->appendPdfPages($generatedPdfAbsolutePath, $workplanPdfAbsPath);
            }

            $formName = trim((string) ($form->name ?? 'Form'));
            $document = Document::query()->create([
                'description_text' => $formName.' submission #'.(int) $submission->getKey(),
                'author' => (int) ($submission->submitted_by ?? 0) ?: null,
                'link' => $generatedPdfRelativePath,
            ]);

            $generatedDocument = GeneratedDocument::query()->create([
                'form_submission_id' => (int) $submission->getKey(),
                'template_id' => null,
                'request_id' => $requestId,
                'document_id' => (int) $document->getKey(),
                'generated_by' => $generatedByUserId,
                'docx_path' => null,
                'pdf_path' => $generatedPdfRelativePath,
                'status' => 'generated',
                'generated_at' => now(),
            ]);

            $submission->loadMissing(['submitter', 'organization.detail', 'form']);
            $recipientEmail = $submission->submitter?->user_email;

            if (is_string($recipientEmail) && $recipientEmail !== '') {
                try {
                    Mail::to($recipientEmail)->send(
                        new DocumentGeneratedMail(
                            (string) ($submission->form?->name ?? 'Form'),
                            (string) ($submission->organization?->detail?->name ?? 'Organization'),
                        )
                    );
                } catch (\Throwable $throwable) {
                    report($throwable);
                }
            }

            return $generatedDocument;
        } catch (\Throwable $throwable) {
            $failedRecord = GeneratedDocument::query()->create([
                'form_submission_id' => (int) $submission->getKey(),
                'template_id' => null,
                'request_id' => $requestId,
                'document_id' => null,
                'generated_by' => $generatedByUserId,
                'docx_path' => null,
                'pdf_path' => Storage::disk($disk)->exists($generatedPdfRelativePath)
                    ? $generatedPdfRelativePath
                    : null,
                'status' => 'failed',
                'failure_reason' => $throwable->getMessage(),
                'generated_at' => now(),
            ]);

            throw new RuntimeException(
                'Unable to generate PDF for the request. Failure record #'.(int) $failedRecord->getKey().'. '.$throwable->getMessage(),
                previous: $throwable,
            );
        }
    }

    private function nextGeneratedPdfPath(int $submissionId): string
    {
        $directory = trim((string) config('documents.generated_directory', 'generated-documents'), '/');
        $timestamp = now()->format('Y/m/d');

        return $directory.'/'.$timestamp.'/submission-'.$submissionId.'-'.Str::lower(Str::random(12)).'.pdf';
    }

    private function findWorkplanApprovedPdf(array $payload, string $disk): ?string
    {
        $workplanId = (int) ($payload['workplan_id'] ?? 0);
        if ($workplanId <= 0) {
            return null;
        }

        $workplan = Workplan::find($workplanId);
        if (! $workplan) {
            Log::warning("DocumentGenerationService: workplan #{$workplanId} not found; skipping PDF merge.");
            return null;
        }

        $workplanForm = \App\Forms\SystemFunction::form(\App\Forms\SystemFunction::NEW_WORKPLAN);
        if (! $workplanForm) {
            Log::warning('DocumentGenerationService: workplan form not configured; skipping PDF merge.');
            return null;
        }

        $matchedSubmission = FormSubmission::query()
            ->where('form_id', (int) $workplanForm->getKey())
            ->where('organization_id', $workplan->organization_id)
            ->orderByDesc('submitted_at')
            ->get()
            ->first(function ($sub) use ($workplan) {
                return (int) (((array) ($sub->payload ?? []))['semester_id'] ?? 0) === (int) $workplan->semester_id;
            });

        if (! $matchedSubmission) {
            Log::warning("DocumentGenerationService: no workplan form submission found for workplan #{$workplanId}; skipping PDF merge.");
            return null;
        }

        $actionRequest = ActionRequest::query()
            ->where('action_type', FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION)
            ->where('organization_id', $workplan->organization_id)
            ->orderByDesc('requested_at')
            ->get()
            ->first(fn ($r) => (int) (((array) ($r->payload ?? []))['submission_id'] ?? 0) === (int) $matchedSubmission->getKey());

        if (! $actionRequest) {
            Log::warning("DocumentGenerationService: no action request found for workplan submission #{$matchedSubmission->getKey()}; skipping PDF merge.");
            return null;
        }

        $approval = Approval::query()
            ->where('request', $actionRequest->request_id)
            ->where('is_rejected', false)
            ->first();

        if (! $approval) {
            Log::warning("DocumentGenerationService: workplan request #{$actionRequest->request_id} not yet approved; skipping PDF merge.");
            return null;
        }

        $generatedDoc = GeneratedDocument::query()
            ->where('request_id', $actionRequest->request_id)
            ->where('status', 'generated')
            ->first();

        if (! $generatedDoc || (string) ($generatedDoc->pdf_path ?? '') === '') {
            Log::warning("DocumentGenerationService: workplan generated document not found for request #{$actionRequest->request_id}; skipping PDF merge.");
            return null;
        }

        $absPath = Storage::disk($disk)->path((string) $generatedDoc->pdf_path);
        if (! is_file($absPath)) {
            Log::warning("DocumentGenerationService: workplan PDF file missing on disk ({$absPath}); skipping PDF merge.");
            return null;
        }

        return $absPath;
    }

    private function appendPdfPages(string $mainPdfAbsPath, string $appendPdfAbsPath): void
    {
        $tmpPath = $mainPdfAbsPath.'.merge_'.Str::random(8).'.pdf';

        $binary = (string) config('documents.pdfunite_binary', 'pdfunite');
        $timeout = max((int) config('documents.pdf_timeout', 120), 30);

        $process = new Process([$binary, $mainPdfAbsPath, $appendPdfAbsPath, $tmpPath]);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($tmpPath)) {
            @unlink($tmpPath);

            // Fallback: ghostscript
            $process = new Process([
                'gs', '-dBATCH', '-dNOPAUSE', '-q',
                '-sDEVICE=pdfwrite',
                '-sOutputFile='.$tmpPath,
                $mainPdfAbsPath,
                $appendPdfAbsPath,
            ]);
            $process->setTimeout($timeout);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($tmpPath)) {
                @unlink($tmpPath);
                Log::warning('DocumentGenerationService: PDF merge failed (pdfunite and ghostscript both failed); recognition PDF generated without workplan attachment.');
                return;
            }
        }

        rename($tmpPath, $mainPdfAbsPath);
    }
}
