<?php

namespace App\Services;

use App\Helpers\FormTemplateHelper;
use App\Mail\DocumentGeneratedMail;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Generates one printable PDF per active Word template for a form submission:
 * each `.docx` authored in the form builder's "Printed template" step is
 * populated with the submission's values and converted to PDF.
 *
 * This supersedes the intermediate dompdf pipeline, which rendered a separate
 * rich-text `pdf_template.html` blob. Those blobs are migrated to `.docx` on
 * first open by {@see FormPrintTemplateService}, so nothing reads them here any
 * more. The request/approval plumbing (action encode/decode via
 * {@see FormTemplateHelper}) is unchanged, so existing workflows keep working.
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
        return $this->generateAllFromApprovedRequest($request, $generatedByUserId)->first();
    }

    /** @return Collection<int, GeneratedDocument> */
    public function generateAllFromApprovedRequest(ActionRequest $request, int $generatedByUserId): Collection
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

        return $this->generateAllFromSubmission(
            $submission,
            (int) $request->getKey(),
            $generatedByUserId,
        );
    }

    /** Return the primary document while still printing all active templates. */
    public function generateFromSubmission(
        FormSubmission $submission,
        ?int $requestId,
        int $generatedByUserId,
    ): GeneratedDocument {
        return $this->generateAllFromSubmission($submission, $requestId, $generatedByUserId)->first();
    }

    /** @return Collection<int, GeneratedDocument> */
    public function generateAllFromSubmission(
        FormSubmission $submission,
        ?int $requestId,
        int $generatedByUserId,
    ): Collection {
        $disk = (string) config('documents.disk', 'public');
        $generated = collect();
        $currentPdfPath = null;

        try {
            $form = $submission->form ?: Form::query()->find($submission->form_id);
            if (! $form) {
                throw new RuntimeException('Form for this submission was not found.');
            }

            $fields = FormDescription::query()
                ->where('form_id', (int) $submission->form_id)
                ->orderBy('field_order')
                ->get();

            // The printed document is the form's Word template (authored in the
            // form builder's OnlyOffice step), populated with this submission
            // and converted to PDF. Page setup, fonts and headers now live in
            // the .docx itself rather than in a separate config blob.
            $printTemplates = app(\App\Services\FormPrintTemplateService::class)->resolveAll($form);
            if ($printTemplates->isEmpty()) {
                throw new RuntimeException('No printable templates are available for this form.');
            }

            // Resolve the submitter's profile + organization so `{{profile.*}}`
            // tokens print from them. `profile` is a FK column that shadows the
            // relation, so the related model must be loaded explicitly.
            $submitter = $submission->submitted_by
                ? User::find((int) $submission->submitted_by)
                : null;
            $submitterProfile = $submitter?->profile()->first();
            $submitterOrganization = OrganizationField::resolveOrganization($submitter);

            $templateData = app(\App\Forms\DocxTemplateData::class)->build(
                (array) $submission->payload,
                $fields,
                $submitterProfile,
                $submitterOrganization,
                $disk,
            );

            // An After Event Report also prints its event's record and the
            // original New Event form answers (`{{eventinfo.*}}`/`{{event.*}}`).
            if ($form->system_function === \App\Forms\SystemFunction::AFTER_EVENT_REPORT) {
                $eventData = app(\App\Forms\AfterEventTokenData::class)->build($submission, $disk);
                $templateData['values'] = $eventData['values'] + $templateData['values'];
                $templateData['images'] = $eventData['images'] + $templateData['images'];
            }

            $docxService = app(\App\Services\DocxTemplateService::class);
            foreach ($printTemplates as $index => $printTemplate) {
                $generatedPdfRelativePath = $this->nextGeneratedPdfPath((int) $submission->getKey());
                $currentPdfPath = $generatedPdfRelativePath;
                $generatedPdfAbsolutePath = Storage::disk($disk)->path($generatedPdfRelativePath);
                File::ensureDirectoryExists(dirname($generatedPdfAbsolutePath));
                $populatedDocxPath = $docxService->populate(
                    $printTemplate,
                    $templateData['values'],
                    $templateData['images'],
                );

                try {
                    $pdfAbsolutePath = $docxService->toPdf($populatedDocxPath);
                    Storage::disk($disk)->put($generatedPdfRelativePath, File::get($pdfAbsolutePath));
                } finally {
                    File::deleteDirectory(dirname($populatedDocxPath));
                }

                if ($index === 0) {
                    $workplanPdfAbsPath = $this->findWorkplanApprovedPdf((array) $submission->payload, $disk);
                    if ($workplanPdfAbsPath !== null) {
                        $this->appendPdfPages($generatedPdfAbsolutePath, $workplanPdfAbsPath);
                    }
                }

                $formName = trim((string) ($form->name ?? 'Form'));
                $document = Document::query()->create([
                    'description_text' => $formName.' - '.(string) ($printTemplate->template_name ?: $formName).' submission #'.(int) $submission->getKey(),
                    'author' => (int) ($submission->submitted_by ?? 0) ?: null,
                    'link' => $generatedPdfRelativePath,
                ]);

                $generated->push(GeneratedDocument::query()->create([
                    'form_submission_id' => (int) $submission->getKey(),
                    'template_id' => (int) $printTemplate->getKey(),
                    'request_id' => $requestId,
                    'document_id' => (int) $document->getKey(),
                    'generated_by' => $generatedByUserId,
                    'docx_path' => null,
                    'pdf_path' => $generatedPdfRelativePath,
                    'status' => 'generated',
                    'generated_at' => now(),
                ]));
                $currentPdfPath = null;
            }

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

            return $generated;
        } catch (\Throwable $throwable) {
            if ($currentPdfPath !== null) {
                Storage::disk($disk)->delete($currentPdfPath);
            }
            foreach ($generated as $item) {
                Storage::disk($disk)->delete((string) $item->pdf_path);
                $item->document?->delete();
                $item->delete();
            }
            $failedRecord = GeneratedDocument::query()->create([
                'form_submission_id' => (int) $submission->getKey(),
                'template_id' => null,
                'request_id' => $requestId,
                'document_id' => null,
                'generated_by' => $generatedByUserId,
                'docx_path' => null,
                'pdf_path' => null,
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
            ->orderBy('generated_document_id')
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
