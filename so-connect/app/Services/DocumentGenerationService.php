<?php

namespace App\Services;

use App\Helpers\FormTemplateHelper;
use App\Mail\DocumentGeneratedMail;
use App\Models\Approval;
use App\Models\Document;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\GeneratedDocument;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Models\Template;
use App\Models\Workplan;
use DOMDocument;
use DOMElement;
use DOMXPath;
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
        $requestType = app(RequestTypeService::class)->resolveSystemType(
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
        $requestType = app(RequestTypeService::class)->resolveSystemType(
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
        $requestType = app(RequestTypeService::class)->resolveSystemType(
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

            $isAccomplishmentReport = $formRouteName === 'accomplishment-report';
            $accomplishmentPhotoWidth = null;

            foreach ($replacementMap as $placeholderKey => $value) {
                if (isset($imagePlaceholderKeys[$placeholderKey])) {
                    $rawValue = $originalPayload[$placeholderKey] ?? null;
                    if ($isAccomplishmentReport && $placeholderKey === 'photos') {
                        $paths = $this->resolveImageAbsolutePaths($rawValue, $disk);
                        if (count($paths) > 0) {
                            $processor->cloneRow($placeholderKey, count($paths));
                            if ($accomplishmentPhotoWidth === null) {
                                $accomplishmentPhotoWidth = $this->findTableCellWidthPixels(
                                    $preprocessedTemplatePath,
                                    $placeholderKey
                                );
                            }
                            if ($accomplishmentPhotoWidth === null) {
                                $accomplishmentPhotoWidth = $this->findPageContentWidthPixels(
                                    $preprocessedTemplatePath
                                );
                            }
                            foreach ($paths as $index => $path) {
                                $options = ['path' => $path];
                                if ($accomplishmentPhotoWidth !== null) {
                                    $info = @getimagesize($path);
                                    if (is_array($info) && ($info[0] ?? 0) > 0 && ($info[1] ?? 0) > 0) {
                                        $options['width'] = $accomplishmentPhotoWidth;
                                        $options['height'] = (int) round($accomplishmentPhotoWidth * ($info[1] / $info[0]));
                                    } else {
                                        $options['width'] = $accomplishmentPhotoWidth;
                                    }
                                }
                                $processor->setImageValue($placeholderKey.'#'.($index + 1), $options);
                            }
                        } else {
                            $processor->setValue($placeholderKey, '');
                        }

                        continue;
                    }
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
                'image/png' => @imagecreatefrompng($path),
                default => null,
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

    /**
     * @return string[]
     */
    private function resolveImageAbsolutePaths(mixed $rawValue, string $disk): array
    {
        $items = [];

        if (is_array($rawValue)) {
            $items = $rawValue;
        } elseif (is_string($rawValue)) {
            $trimmed = trim($rawValue);
            if ($trimmed !== '') {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $items = $decoded;
                } else {
                    $items = [$trimmed];
                }
            }
        }

        $items = array_values(array_filter(
            $items,
            fn ($path) => is_string($path) && trim($path) !== ''
        ));

        return array_values(array_filter(
            array_map(fn ($path) => Storage::disk($disk)->path((string) $path), $items),
            'is_file'
        ));
    }

    private function findTableCellWidthPixels(string $docxAbsolutePath, string $placeholderKey): ?int
    {
        if (! is_file($docxAbsolutePath)) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($docxAbsolutePath) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return null;
        }

        $needle = '${'.$placeholderKey.'}';

        $dom = new DOMDocument;
        $prevErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        if (! $loaded) {
            return null;
        }

        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', $namespace);

        $cellNode = $xpath->query('//w:tc[.//w:t[contains(., "'.$needle.'")]]')->item(0);
        if (! $cellNode instanceof DOMElement) {
            $cellNode = $this->findTableCellByJoinedText($xpath, $needle);
        }
        if (! $cellNode instanceof DOMElement) {
            return null;
        }

        $tcW = $xpath->query('./w:tcPr/w:tcW', $cellNode)->item(0);
        if ($tcW instanceof DOMElement) {
            $type = $this->wordAttr($tcW, $namespace, 'type');
            $widthValue = $this->wordAttr($tcW, $namespace, 'w');
            $twips = (int) $widthValue;
            if ($type !== 'pct' && $twips > 0) {
                return max(1, (int) round($twips / 15));
            }
        }

        $tableNode = $xpath->query('ancestor::w:tbl', $cellNode)->item(0);
        if (! $tableNode instanceof DOMElement) {
            return null;
        }

        $gridCols = $xpath->query('./w:tblGrid/w:gridCol', $tableNode);
        if (! $gridCols || $gridCols->length === 0) {
            return null;
        }

        $columnWidths = [];
        foreach ($gridCols as $gridCol) {
            if (! $gridCol instanceof DOMElement) {
                continue;
            }
            $widthValue = $this->wordAttr($gridCol, $namespace, 'w');
            $columnWidths[] = (int) $widthValue;
        }

        $rowNode = $xpath->query('ancestor::w:tr', $cellNode)->item(0);
        if (! $rowNode instanceof DOMElement) {
            return null;
        }

        $startIndex = 0;
        $previousCells = $xpath->query('preceding-sibling::w:tc', $cellNode);
        if ($previousCells) {
            foreach ($previousCells as $prevCell) {
                if ($prevCell instanceof DOMElement) {
                    $startIndex += $this->cellGridSpan($xpath, $prevCell, $namespace);
                }
            }
        }

        $span = $this->cellGridSpan($xpath, $cellNode, $namespace);
        $twips = 0;
        for ($i = 0; $i < $span; $i++) {
            $twips += (int) ($columnWidths[$startIndex + $i] ?? 0);
        }

        if ($twips <= 0) {
            return null;
        }

        return max(1, (int) round($twips / 15));
    }

    private function findPageContentWidthPixels(string $docxAbsolutePath): ?int
    {
        if (! is_file($docxAbsolutePath)) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($docxAbsolutePath) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return null;
        }

        $dom = new DOMDocument;
        $prevErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        if (! $loaded) {
            return null;
        }

        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', $namespace);

        $sect = $xpath->query('//w:sectPr')->item(0);
        if (! $sect instanceof DOMElement) {
            return null;
        }

        $pgSz = $xpath->query('./w:pgSz', $sect)->item(0);
        $pgMar = $xpath->query('./w:pgMar', $sect)->item(0);
        if (! $pgSz instanceof DOMElement || ! $pgMar instanceof DOMElement) {
            return null;
        }

        $pageWidth = (int) $this->wordAttr($pgSz, $namespace, 'w');
        $leftMargin = (int) $this->wordAttr($pgMar, $namespace, 'left');
        $rightMargin = (int) $this->wordAttr($pgMar, $namespace, 'right');

        $contentWidth = $pageWidth - $leftMargin - $rightMargin;
        if ($contentWidth <= 0) {
            return null;
        }

        return max(1, (int) round($contentWidth / 15));
    }

    private function wordAttr(DOMElement $element, string $namespace, string $localName): string
    {
        $value = $element->getAttributeNS($namespace, $localName);
        if ($value !== '') {
            return $value;
        }

        return $element->getAttribute('w:'.$localName);
    }

    private function findTableCellByJoinedText(DOMXPath $xpath, string $needle): ?DOMElement
    {
        $cells = $xpath->query('//w:tc');
        if (! $cells) {
            return null;
        }

        foreach ($cells as $cell) {
            if (! $cell instanceof DOMElement) {
                continue;
            }
            $texts = $xpath->query('.//w:t', $cell);
            if (! $texts) {
                continue;
            }
            $joined = '';
            foreach ($texts as $textNode) {
                $joined .= $textNode->textContent ?? '';
            }
            if ($joined !== '' && str_contains($joined, $needle)) {
                return $cell;
            }
        }

        return null;
    }

    private function cellGridSpan(DOMXPath $xpath, DOMElement $cellNode, string $namespace): int
    {
        $span = 1;
        $gridSpanNode = $xpath->query('./w:tcPr/w:gridSpan', $cellNode)->item(0);
        if ($gridSpanNode instanceof DOMElement) {
            $value = (int) $this->wordAttr($gridSpanNode, $namespace, 'val');
            if ($value > 1) {
                $span = $value;
            }
        }

        return $span;
    }

    private function preprocessTemplateDocx(string $absolutePath): string
    {
        $tempPath = sys_get_temp_dir().'/phpword_'.Str::random(12).'.docx';
        copy($absolutePath, $tempPath);

        $zip = new ZipArchive;
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
        // Word sometimes splits {{ into two consecutive runs (e.g. one run contains "{" and
        // the next run contains "{"), with only XML elements like <w:proofErr> between them.
        // Merge those split braces so the main regex below can find {{...}} reliably.
        $xml = (string) (preg_replace('/\{(<\/w:t>[^{}]*<w:t[^>]*>)\{/', '{{$1', $xml) ?? $xml);

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
