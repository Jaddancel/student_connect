<?php

namespace App\Services;

use App\Mail\DocumentGeneratedMail;
use App\Helpers\FormTemplateHelper;
use App\Models\Approval;
use App\Models\Document;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\Template;
use App\Models\Workplan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        $template = Template::query()
            ->where('form_id', $formId)
            ->where(function ($q) use ($organizationId) {
                if ($organizationId > 0) {
                    $q->where('organization_id', $organizationId)
                        ->orWhereNull('organization_id');
                } else {
                    $q->whereNull('organization_id');
                }
            })
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN organization_id IS NOT NULL THEN 0 ELSE 1 END')
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
        ?int $requestId,
        int $generatedByUserId,
    ): GeneratedDocument {
        $disk = (string) config('documents.disk', 'public');
        $templatePath = (string) $template->docx_path;

        if ($templatePath === '' || ! Storage::disk($disk)->exists($templatePath)) {
            throw new RuntimeException('Template DOCX file is missing in storage.');
        }

        $templateAbsolutePath = Storage::disk($disk)->path($templatePath);

        $mappings = $template->mappings()->with('field')->get();

        $missingRequiredFields = FormTemplateHelper::missingRequiredFields($mappings, (array) $submission->payload);
        $formRouteName = Form::query()->where('id', $submission->form_id)->value('route_name');
        if ($formRouteName === 'joint-statement') {
            $missingRequiredFields = array_values(array_filter(
                $missingRequiredFields,
                fn ($field) => $field !== 'adviser1signature'
            ));
        }

        if (! empty($missingRequiredFields)) {
            throw new RuntimeException('Submission is missing required fields: '.implode(', ', $missingRequiredFields));
        }

        $replacementMap = FormTemplateHelper::buildReplacementMap($mappings, (array) $submission->payload);

        // Collect placeholder keys whose FormDescription field_type is 'file' (image upload).
        $imagePlaceholderKeys = $mappings
            ->filter(fn ($m) => ($m->field?->field_type ?? '') === 'file')
            ->map(fn ($m) => FormTemplateHelper::normalizeFieldKey(
                (string) ($m->placeholder_key ?: $m->field_key)
            ))
            ->flip()
            ->all();

        $generatedDocxRelativePath = $this->nextGeneratedDocxPath((int) $submission->getKey());
        $generatedDocxAbsolutePath = Storage::disk($disk)->path($generatedDocxRelativePath);

        File::ensureDirectoryExists(dirname($generatedDocxAbsolutePath));

        $preprocessedTemplatePath = null;
        $tempCompositePaths = [];

        try {
            $preprocessedTemplatePath = $this->preprocessTemplateDocx($templateAbsolutePath);
            $processor = new TemplateProcessor($preprocessedTemplatePath);

            $originalPayload = (array) $submission->payload;

            foreach ($replacementMap as $placeholderKey => $value) {
                if (isset($imagePlaceholderKeys[$placeholderKey])) {
                    $rawValue = $originalPayload[$placeholderKey] ?? null;
                    if (is_array($rawValue) && count($rawValue) > 0) {
                        $paths = array_values(array_filter(
                            array_map(fn ($p) => Storage::disk($disk)->path((string) $p), $rawValue),
                            'is_file'
                        ));
                        if (count($paths) > 1) {
                            $composite = $this->compositeImagesVertically($paths);
                            if ($composite) {
                                $tempCompositePaths[] = $composite;
                                $processor->setImageValue($placeholderKey, ['path' => $composite, 'ratio' => true]);
                                continue;
                            }
                        } elseif (count($paths) === 1) {
                            $processor->setImageValue($placeholderKey, ['path' => $paths[0], 'ratio' => true]);
                            continue;
                        }
                    }
                    if ($value !== '') {
                        $absoluteImagePath = Storage::disk($disk)->path($value);
                        if (is_file($absoluteImagePath)) {
                            $processor->setImageValue($placeholderKey, ['path' => $absoluteImagePath, 'ratio' => true]);
                            continue;
                        }
                    }
                    $processor->setValue($placeholderKey, '');
                    continue;
                }
                $processor->setValue($placeholderKey, $value);
            }

            $processor->saveAs($generatedDocxAbsolutePath);
            foreach ($tempCompositePaths as $tmp) {
                @unlink($tmp);
            }
            $tempCompositePaths = [];
            @unlink($preprocessedTemplatePath);
            $preprocessedTemplatePath = null;

            $pdfAbsolutePath = $this->convertDocxToPdf($generatedDocxAbsolutePath);

            $workplanPdfAbsPath = $this->findWorkplanApprovedPdf((array) $submission->payload, $disk);
            if ($workplanPdfAbsPath !== null) {
                $this->appendPdfPages($pdfAbsolutePath, $workplanPdfAbsPath);
            }

            $waiverPdfAbsPath = $this->resolveWaiverPdf((array) $submission->payload, $disk, $tempCompositePaths);
            if ($waiverPdfAbsPath !== null) {
                $this->appendPdfPages($pdfAbsolutePath, $waiverPdfAbsPath);
            }
            foreach ($tempCompositePaths as $tmp) {
                @unlink($tmp);
            }
            $tempCompositePaths = [];

            $generatedPdfRelativePath = $this->relativePathFromDiskAbsolute($pdfAbsolutePath, $disk);

            $formName = trim((string) ($submission->form?->name ?? 'Form'));
            $document = Document::query()->create([
                'description_text' => $formName.' submission #'.(int) $submission->getKey(),
                'author' => (int) ($submission->submitted_by ?? 0) ?: null,
                'link' => $generatedPdfRelativePath,
            ]);

            $generatedDocument = GeneratedDocument::query()->create([
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
            if ($preprocessedTemplatePath !== null) {
                @unlink($preprocessedTemplatePath);
            }
            foreach ($tempCompositePaths as $tmp) {
                @unlink($tmp);
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

    /**
     * Stack multiple images vertically into a single JPEG temp file.
     * Returns the absolute path to the composite, or null on failure.
     *
     * @param  string[]  $absolutePaths
     */
    private function compositeImagesVertically(array $absolutePaths): ?string
    {
        $frames = [];
        $maxWidth = 0;
        $totalHeight = 0;
        $gap = 10;

        foreach ($absolutePaths as $path) {
            $info = @getimagesize($path);
            if (! $info) {
                continue;
            }
            $img = match ($info['mime']) {
                'image/jpeg' => @imagecreatefromjpeg($path),
                'image/png'  => @imagecreatefrompng($path),
                default      => null,
            };
            if (! $img) {
                continue;
            }
            $w = imagesx($img);
            $h = imagesy($img);
            $frames[] = ['img' => $img, 'w' => $w, 'h' => $h];
            $maxWidth = max($maxWidth, $w);
            $totalHeight += $h;
        }

        if (empty($frames)) {
            return null;
        }

        $totalHeight += $gap * (count($frames) - 1);

        $canvas = imagecreatetruecolor($maxWidth, $totalHeight);
        if (! $canvas) {
            foreach ($frames as $f) {
                imagedestroy($f['img']);
            }
            return null;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        $y = 0;
        foreach ($frames as $f) {
            imagecopy($canvas, $f['img'], 0, $y, 0, 0, $f['w'], $f['h']);
            $y += $f['h'] + $gap;
            imagedestroy($f['img']);
        }

        $tmpPath = sys_get_temp_dir().'/accomplishment_composite_'.Str::random(8).'.jpg';
        $saved = imagejpeg($canvas, $tmpPath, 90);
        imagedestroy($canvas);

        return ($saved && is_file($tmpPath)) ? $tmpPath : null;
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

        return $this->convertToPdfViaLibreOffice($docxAbsolutePath);
    }

    /**
     * Convert any LibreOffice-supported file (DOCX, JPG, PNG, …) to a PDF that
     * sits next to the source file, and return the PDF's absolute path.
     */
    private function convertToPdfViaLibreOffice(string $inputAbsolutePath): string
    {
        if (! is_file($inputAbsolutePath)) {
            throw new RuntimeException('Source file for PDF conversion was not found.');
        }

        $outDir = dirname($inputAbsolutePath);
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
            $inputAbsolutePath,
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

        $pdfAbsolutePath = preg_replace('/\.[^.\/\\\\]+$/', '.pdf', $inputAbsolutePath);

        if (! is_string($pdfAbsolutePath) || ! is_file($pdfAbsolutePath)) {
            throw new RuntimeException('PDF conversion did not produce an output file.');
        }

        return $pdfAbsolutePath;
    }

    /**
     * Resolve the uploaded parent/guardian waiver into an absolute PDF path
     * ready to append as a separate page. PDF uploads are used as-is; image
     * uploads are converted to a temporary PDF (tracked for later cleanup).
     *
     * @param  list<string>  $tempPaths  collects temp files created here
     */
    private function resolveWaiverPdf(array $payload, string $disk, array &$tempPaths): ?string
    {
        $relativePath = (string) ($payload['parentGuardianWaiver'] ?? '');
        if ($relativePath === '' || ! Storage::disk($disk)->exists($relativePath)) {
            return null;
        }

        $absolutePath = Storage::disk($disk)->path($relativePath);
        if (! is_file($absolutePath)) {
            return null;
        }

        if (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'pdf') {
            return $absolutePath;
        }

        // Image upload (jpg/jpeg/png): copy to a temp file and convert to PDF so
        // the original stored upload is left untouched.
        $tmpImage = sys_get_temp_dir().'/waiver_'.Str::random(8).'.'.pathinfo($absolutePath, PATHINFO_EXTENSION);
        if (! @copy($absolutePath, $tmpImage)) {
            Log::warning("DocumentGenerationService: unable to stage waiver image for conversion ({$absolutePath}); skipping waiver page.");
            return null;
        }
        $tempPaths[] = $tmpImage;

        try {
            $waiverPdf = $this->convertToPdfViaLibreOffice($tmpImage);
            $tempPaths[] = $waiverPdf;

            return $waiverPdf;
        } catch (\Throwable $throwable) {
            Log::warning('DocumentGenerationService: waiver image-to-PDF conversion failed; skipping waiver page. '.$throwable->getMessage());

            return null;
        }
    }

    private function nextGeneratedDocxPath(int $submissionId): string
    {
        $directory = trim((string) config('documents.generated_directory', 'generated-documents'), '/');
        $timestamp = now()->format('Y/m/d');

        return $directory.'/'.$timestamp.'/submission-'.$submissionId.'-'.Str::lower(Str::random(12)).'.docx';
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

        $workplanForm = Form::query()->where('route_name', 'workplan')->first();
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
        $timeout = max((int) config('documents.libreoffice.timeout', 120), 30);

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

    private function relativePathFromDiskAbsolute(string $absolutePath, string $disk): string
    {
        $diskRoot = rtrim(Storage::disk($disk)->path(''), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($absolutePath, $diskRoot)) {
            throw new RuntimeException('Generated file path is outside the configured disk root.');
        }

        return str_replace('\\', '/', ltrim(substr($absolutePath, strlen($diskRoot)), DIRECTORY_SEPARATOR));
    }
}
