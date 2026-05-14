<?php

namespace App\Services;

use App\Helpers\FormTemplateHelper;
use App\Models\Document;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\Template;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class DocumentGenerationService
{
    public function createDocumentGenerationRequest(
        ?int $organizationId,
        int $submissionId,
        int $formId,
        int $requesterUserId,
    ): ActionRequest {
        $requestType = app(\App\Services\RequestTypeService::class)->resolveSystemType(
            RequestType::SYSTEM_KEY_FORM_GENERATION,
            'Form Generation Request',
            RequestType::CATEGORY_ORGANIZATION,
            $requesterUserId,
        );

        $actionRequest = ActionRequest::query()->create([
            'action' => FormTemplateHelper::encodeDocumentGenerationAction(
                (int) ($organizationId ?? 0),
                $submissionId,
                $formId,
                $requesterUserId,
            ),
            'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
            'request_type_id' => (int) $requestType->getKey(),
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

        app(RequestApprovalService::class)->autoApproveIfPresident($actionRequest, $requesterUserId);

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

        app(RequestApprovalService::class)->autoApproveIfPresident($actionRequest, $requesterUserId);

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

        app(RequestApprovalService::class)->autoApproveIfPresident($actionRequest, $uploaderUserId);

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

        $template = Template::query()
            ->where('form_id', $formId)
            ->where(function ($q) use ($organizationId) {
                if ($organizationId > 0) {
                    $q->where('organization_id', $organizationId);
                } else {
                    $q->whereNull('organization_id');
                }
            })
            ->where('is_active', true)
            ->orderByDesc('version')
            ->with(['mappings.field'])
            ->first();

        if (! $template) {
            throw new RuntimeException('No active template is available for this form.');
        }

        return $this->generateFromSubmission(
            $submission,
            $template,
            (int) $request->getKey(),
            $generatedByUserId,
        );
    }

    public function generateFromSubmission(
        FormSubmission $submission,
        Template $template,
        int $requestId,
        int $generatedByUserId,
    ): GeneratedDocument {
        $disk = (string) config('documents.disk', 'public');
        $templatePath = (string) $template->docx_path;

        if ($templatePath === '' || ! Storage::disk($disk)->exists($templatePath)) {
            throw new RuntimeException('Template DOCX file is missing in storage.');
        }

        $templateAbsolutePath = Storage::disk($disk)->path($templatePath);

        $mappings = $template->mappings()->get();

        $missingRequiredFields = FormTemplateHelper::missingRequiredFields($mappings, (array) $submission->payload);

        if (! empty($missingRequiredFields)) {
            throw new RuntimeException('Submission is missing required fields: '.implode(', ', $missingRequiredFields));
        }

        $replacementMap = FormTemplateHelper::buildReplacementMap($mappings, (array) $submission->payload);

        $generatedDocxRelativePath = $this->nextGeneratedDocxPath((int) $submission->getKey());
        $generatedDocxAbsolutePath = Storage::disk($disk)->path($generatedDocxRelativePath);

        File::ensureDirectoryExists(dirname($generatedDocxAbsolutePath));

        $preprocessedTemplatePath = null;

        try {
            $preprocessedTemplatePath = $this->preprocessTemplateDocx($templateAbsolutePath);
            $processor = new TemplateProcessor($preprocessedTemplatePath);

            foreach ($replacementMap as $fieldKey => $value) {
                $processor->setValue($fieldKey, $value);
            }

            $processor->saveAs($generatedDocxAbsolutePath);
            @unlink($preprocessedTemplatePath);
            $preprocessedTemplatePath = null;

            $pdfAbsolutePath = $this->convertDocxToPdf($generatedDocxAbsolutePath);
            $generatedPdfRelativePath = $this->relativePathFromDiskAbsolute($pdfAbsolutePath, $disk);

            $formName = trim((string) ($submission->form?->name ?? 'Form'));
            $document = Document::query()->create([
                'description_text' => $formName.' submission #'.(int) $submission->getKey(),
                'author' => (int) ($submission->submitted_by ?? 0) ?: null,
                'link' => $generatedPdfRelativePath,
            ]);

            return GeneratedDocument::query()->create([
                'form_submission_id' => (int) $submission->getKey(),
                'template_id' => (int) $template->getKey(),
                'request_id' => $requestId,
                'document_id' => (int) $document->getKey(),
                'generated_by' => $generatedByUserId,
                'docx_path' => $generatedDocxRelativePath,
                'pdf_path' => $generatedPdfRelativePath,
                'status' => 'generated',
                'generated_at' => now(),
            ]);
        } catch (\Throwable $throwable) {
            if ($preprocessedTemplatePath !== null) {
                @unlink($preprocessedTemplatePath);
            }

            $failedRecord = GeneratedDocument::query()->create([
                'form_submission_id' => (int) $submission->getKey(),
                'template_id' => (int) $template->getKey(),
                'request_id' => $requestId,
                'document_id' => null,
                'generated_by' => $generatedByUserId,
                'docx_path' => Storage::disk($disk)->exists($generatedDocxRelativePath)
                    ? $generatedDocxRelativePath
                    : 'N/A',
                'pdf_path' => null,
                'status' => 'failed',
                'failure_reason' => $throwable->getMessage(),
                'generated_at' => now(),
            ]);

            throw new RuntimeException(
                'Unable to generate PDF for the approved request. Failure record #'.(int) $failedRecord->getKey().'. '.$throwable->getMessage(),
                previous: $throwable,
            );
        }
    }

    private function preprocessTemplateDocx(string $absolutePath): string
    {
        $tempPath = sys_get_temp_dir().'/phpword_'.Str::random(12).'.docx';
        copy($absolutePath, $tempPath);

        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== true) {
            throw new RuntimeException('Unable to preprocess template DOCX for generation.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name)) {
                continue;
            }
            $isTarget = $name === 'word/document.xml'
                || (bool) preg_match('/^word\/(header|footer)\d*\.xml$/', $name);
            if (! $isTarget) {
                continue;
            }
            $xml = $zip->getFromName($name);
            if (! is_string($xml) || $xml === '') {
                continue;
            }
            $zip->deleteName($name);
            $zip->addFromString($name, $this->convertBraceMacrosToPhpWord($xml));
        }

        $zip->close();

        return $tempPath;
    }

    private function convertBraceMacrosToPhpWord(string $xml): string
    {
        // Match {{fieldname}} or {{fieldname#}} that may span multiple XML runs.
        // [^}]* stops at the first } so it can't overshoot to a later placeholder.
        return preg_replace_callback(
            '/\{\{([^}]*)\}\}/s',
            function ($match) {
                $inner = trim(strip_tags($match[1]));
                $isMultiline = str_ends_with($inner, '#');
                $key = $isMultiline ? substr($inner, 0, -1) : $inner;
                $key = trim(preg_replace('/[^a-z0-9_]+/i', '_', strtolower($key)), '_');
                if ($key === '') {
                    return $match[0];
                }

                return '${'.$key.'}';
            },
            $xml
        ) ?? $xml;
    }

    private function convertDocxToPdf(string $docxAbsolutePath): string
    {
        if (! is_file($docxAbsolutePath)) {
            throw new RuntimeException('Generated DOCX file was not created.');
        }

        $outDir = dirname($docxAbsolutePath);
        $binary = (string) config('documents.libreoffice.binary', 'soffice');
        $timeout = max((int) config('documents.libreoffice.timeout', 120), 30);

        $process = new Process([
            $binary,
            '--headless',
            '--norestore',
            '--convert-to',
            'pdf:writer_pdf_Export',
            '--outdir',
            $outDir,
            $docxAbsolutePath,
        ]);

        // Force software rendering — GPU acceleration causes black PDFs in containers.
        $process->setEnv(['SAL_USE_VCLPLUGIN' => 'svp']);

        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput().' '.$process->getOutput());

            throw new RuntimeException(
                'LibreOffice conversion failed. Verify LIBREOFFICE_BINARY and server package install. '.$errorOutput,
            );
        }

        $pdfAbsolutePath = preg_replace('/\.docx$/i', '.pdf', $docxAbsolutePath);

        if (! is_string($pdfAbsolutePath) || ! is_file($pdfAbsolutePath)) {
            throw new RuntimeException('PDF conversion did not produce an output file.');
        }

        return $pdfAbsolutePath;
    }

    private function nextGeneratedDocxPath(int $submissionId): string
    {
        $directory = trim((string) config('documents.generated_directory', 'generated-documents'), '/');
        $timestamp = now()->format('Y/m/d');

        return $directory.'/'.$timestamp.'/submission-'.$submissionId.'-'.Str::lower(Str::random(12)).'.docx';
    }

    private function relativePathFromDiskAbsolute(string $absolutePath, string $disk): string
    {
        $diskRoot = rtrim(Storage::disk($disk)->path(''), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($absolutePath, $diskRoot)) {
            throw new RuntimeException('Generated file path is outside the configured disk root.');
        }

        return str_replace('\\', '/', ltrim(substr($absolutePath, strlen($diskRoot)), DIRECTORY_SEPARATOR));
    }
}
