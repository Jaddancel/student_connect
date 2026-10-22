<?php

namespace App\Services\ManualForm;

use App\Models\ManualFormSession;
use App\Models\ManualFormSessionDocument;
use App\Services\DocumentVision\DocumentVisionClient;
use App\Services\OcrClient;
use App\Services\PdfRasterizer;
use App\Support\SignatureImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Parses a returned scan against a session's frozen reference pages. Each
 * field is read by the most reliable source available:
 *
 *  1. the PDF's own text, when the upload is the downloaded partial PDF with
 *     values typed into it ({@see PdfTextLayerReader});
 *  2. a crop of the field's read region from the aligned scan, read on its own
 *     by the vision model;
 *  3. a whole-page read for fields without an accurate position.
 *
 * Alignment (OCR sidecar) maps uploaded pages onto the reference geometry.
 * Missing, duplicate, or unmatched pages drop the session into a reviewable
 * failure the user can retry from.
 */
class ManualScanParser
{
    /** Bounds sources accurate enough to crop or read typed text from. */
    private const ACCURATE_BOUNDS = ['render', 'cell'];

    public function __construct(
        private readonly OcrClient $ocr,
        private readonly DocumentVisionClient $vision,
        private readonly PdfRasterizer $rasterizer,
        private readonly ?PdfTextLayerReader $textLayer = null,
    ) {}

    public function parse(string $sessionId, ?int $documentId = null): void
    {
        $session = ManualFormSession::find($sessionId);
        $document = $documentId === null ? null : $session?->documents()->find($documentId);
        if ($session === null || ($documentId !== null && $document === null)) {
            return;
        }
        $target = $document ?? $session;
        if ($target->status !== ManualFormSession::STATUS_PARSING) {
            return;
        }

        $disk = (string) config('documents.disk', 'public');
        $fields = (array) ($target->session_schema['extractable_fields'] ?? []);

        ParseProgress::set($target, 3, 'Checking the PDF for typed text…');
        $typed = $this->readTextLayer($target, $disk, $fields);
        $remaining = array_values(array_filter(
            $fields,
            fn ($f) => is_array($f) && ! array_key_exists((string) ($f['key'] ?? ''), $typed),
        ));

        if ($typed !== [] && $remaining === []) {
            $this->complete($session, $document, $target, [
                'values' => $typed,
                'signatures' => [],
                'signature_images' => [],
                'unresolved' => [],
                'sources' => array_fill_keys(array_keys($typed), 'text_layer'),
            ], array_fill_keys(array_keys($typed), 1.0), [], 'pdf-text-layer');

            return;
        }

        ParseProgress::set($target, 8, 'Preparing the scanned pages…');
        $referencePngs = $this->referenceImages($target, $disk);
        $scanBytes = $this->scanBytes($target, $disk);

        if ($referencePngs === [] || $scanBytes === []) {
            $this->fail($target, 'The scan could not be read. Please upload a clearer copy.');

            return;
        }

        ParseProgress::set($target, 12, 'Lining the scan up with the printed form…');
        $alignment = $this->ocr->alignPages(array_column($referencePngs, 'base64'), $scanBytes);
        if (! ($alignment['ok'] ?? false)) {
            $this->fail($target, 'The scan pages could not be aligned. Please try again.');

            return;
        }

        $alignedPages = (array) ($alignment['pages'] ?? []);
        $warnings = (array) ($alignment['warnings'] ?? []);

        $unmatched = array_values(array_filter($alignedPages, fn ($p) => ! ($p['matched'] ?? false)));
        if ($unmatched !== [] || $alignedPages === []) {
            $this->fail(
                $target,
                'Some pages are missing or did not match the printed form. Re-scan every page in order.',
                $warnings,
            );

            return;
        }

        $pages = [];
        foreach ($alignedPages as $aligned) {
            $index = (int) ($aligned['index'] ?? 0);
            $reference = $referencePngs[$index]['base64'] ?? null;
            $scan = $aligned['image'] ?? null;
            if ($reference === null || ! is_string($scan) || $scan === '') {
                continue;
            }
            $pages[] = ['index' => $index, 'reference_image' => $reference, 'scan_image' => $scan];
        }

        if ($pages === []) {
            $this->fail($target, 'No usable pages were produced from the scan.', $warnings);

            return;
        }

        // Signatures with an accurate box are cropped straight from the scan
        // below, so asking the model whether they are present would only add a
        // slow whole-page call whose answer nothing uses.
        $cropSignatures = (bool) config('manual_form.signature_capture.enabled', true);
        $croppedSignatureKeys = $cropSignatures ? array_column(array_filter(
            $remaining,
            fn ($f) => ($f['type'] ?? null) === \App\Forms\FieldType::SIGNATURE
                && in_array($f['bounds_source'] ?? null, self::ACCURATE_BOUNDS, true)
                && $this->plausibleBounds($f['bounds'] ?? null) !== null,
        ), 'key') : [];
        $visionFields = array_values(array_filter(
            $remaining,
            fn ($f) => ! in_array($f['key'] ?? null, $croppedSignatureKeys, true),
        ));

        $result = $visionFields === []
            ? ['ok' => true, 'model' => null, 'values' => [], 'signatures' => [], 'confidence' => [], 'unresolved' => [], 'warnings' => []]
            : $this->vision->extract([
                'known_values' => (array) $session->known_fields,
                'fields' => $this->extractionFields($visionFields),
                'pages' => $pages,
                'crops' => $this->fieldCrops($visionFields, $pages),
                // Model calls are the slow part: they map onto 20–95% of the bar.
                'progress' => function (int $done, int $total, string $label) use ($target): void {
                    $share = $total > 0 ? $done / $total : 0;
                    ParseProgress::set($target, 20 + (int) round(75 * $share), $label);
                },
            ]);

        if (! ($result['ok'] ?? false)) {
            $this->fail($target, 'The reader is unavailable right now. Your draft is saved — try again shortly.', $warnings);

            return;
        }

        ParseProgress::set($target, 97, 'Saving what was read…');
        $signatures = (array) ($result['signatures'] ?? []);
        $signatureImages = $this->captureSignatures($target, $pages, $signatures);
        $unresolved = array_values(array_diff((array) ($result['unresolved'] ?? []), array_keys($typed)));
        foreach ($croppedSignatureKeys as $key) {
            $field = collect($remaining)->firstWhere('key', $key);
            $signatures[$key] = [
                'present' => isset($signatureImages[$key]),
                'page' => (int) ($field['page'] ?? 0),
                'bounds' => $this->plausibleBounds($field['bounds'] ?? null),
            ];
            if (! isset($signatureImages[$key])) {
                $unresolved[] = $key;
            }
        }

        $visionValues = (array) ($result['values'] ?? []);
        $sources = array_fill_keys(array_keys($typed), 'text_layer');
        foreach (array_keys($visionValues) as $key) {
            $sources[$key] = (string) ($result['sources'][$key] ?? 'page');
        }

        $model = $result['model'] ?? null;
        if ($model === null && $typed !== []) {
            $model = 'pdf-text-layer';
        }

        $this->complete($session, $document, $target, [
            'values' => array_merge($visionValues, $typed),
            'signatures' => $signatures,
            'signature_images' => $signatureImages,
            'unresolved' => array_values(array_unique($unresolved)),
            'sources' => $sources,
        ],
            array_merge((array) ($result['confidence'] ?? []), array_fill_keys(array_keys($typed), 1.0)),
            array_values(array_unique(array_merge($warnings, (array) ($result['warnings'] ?? [])))),
            $model,
        );
    }

    /**
     * @param  array<string,mixed>  $parseResult
     * @param  array<string,float>  $confidence
     * @param  array<int,string>  $warnings
     */
    private function complete(
        ManualFormSession $session,
        ?ManualFormSessionDocument $document,
        ManualFormSession|ManualFormSessionDocument $target,
        array $parseResult,
        array $confidence,
        array $warnings,
        ?string $model,
    ): void {
        $target->forceFill([
            'status' => ManualFormSession::STATUS_REVIEW,
            'parse_result' => $parseResult,
            'parse_confidence' => $confidence,
            'parse_warnings' => $warnings,
            'parse_error' => null,
            'parse_model' => $model,
        ])->save();
        ParseProgress::clear($target);
        if ($document) {
            app(ManualFormSessionService::class)->syncDocuments($session);
        }
    }

    /**
     * Values typed directly onto the downloaded partial PDF, read from its text
     * rather than its pixels. Only for a single uploaded PDF; anything else (a
     * photo, several files, a re-laid-out document) yields nothing.
     *
     * @param  array<int,mixed>  $fields
     * @return array<string,mixed>
     */
    private function readTextLayer(ManualFormSession|ManualFormSessionDocument $target, string $disk, array $fields): array
    {
        $scanPaths = array_values((array) $target->scan_paths);
        $reference = (string) ($target->partial_pdf_path ?? '');
        if (count($scanPaths) !== 1 || $reference === ''
            || strtolower(pathinfo((string) $scanPaths[0], PATHINFO_EXTENSION)) !== 'pdf') {
            return [];
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists((string) $scanPaths[0]) || ! $storage->exists($reference)) {
            return [];
        }

        $readable = [];
        foreach ($fields as $field) {
            $region = is_array($field) ? $this->readRegion($field) : null;
            if ($region === null || ($field['type'] ?? null) === \App\Forms\FieldType::SIGNATURE) {
                continue;
            }
            $readable[] = [
                'key' => (string) $field['key'],
                'type' => (string) ($field['type'] ?? 'text'),
                'options' => $field['options'] ?? null,
                'page' => (int) ($field['page'] ?? 0),
                'region' => $region,
                'bounds' => $this->plausibleBounds($field['bounds'] ?? null),
            ];
        }
        if ($readable === []) {
            return [];
        }

        try {
            return ($this->textLayer ?? app(PdfTextLayerReader::class))->read(
                $storage->path((string) $scanPaths[0]),
                $storage->path($reference),
                $readable,
            );
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Where a text field's entry is read from: the schema's `read_bounds`
     * (marker extended down to the next printed text), else — for sessions
     * prepared before it existed — the field bounds padded, mostly downward
     * since entries sit on or below the marker line. Null without an accurate
     * position.
     *
     * @param  array<string,mixed>  $field
     * @return array<int,float>|null
     */
    private function readRegion(array $field): ?array
    {
        if (($field['key'] ?? '') === '' || ! in_array($field['bounds_source'] ?? null, self::ACCURATE_BOUNDS, true)) {
            return null;
        }

        $read = $this->plausibleBounds($field['read_bounds'] ?? null);
        if ($read !== null) {
            return $read;
        }

        $bounds = $this->plausibleBounds($field['bounds'] ?? null);
        if ($bounds === null) {
            return null;
        }
        $padX = (float) config('manual_form.read_region.fallback_pad.x', 0.01);
        $padY = (float) config('manual_form.read_region.fallback_pad.y', 0.02);

        return [
            max(0.0, $bounds[0] - $padX),
            max(0.0, $bounds[1] - 0.004),
            min(1.0, $bounds[2] + $padX),
            min(1.0, $bounds[3] + $padY),
        ];
    }

    /**
     * Each non-signature field with an accurate read region, cropped from its
     * aligned scan page and upscaled so small handwriting fills enough of the
     * model's view. Fields without one are read from the whole page instead.
     *
     * @param  array<int,array<string,mixed>>  $fields
     * @param  array<int,array{index:int,reference_image:string,scan_image:string}>  $pages
     * @return array<int,array{key:string,label:string,type:string,options:mixed,image:string}>
     */
    private function fieldCrops(array $fields, array $pages): array
    {
        $scanByPage = [];
        foreach ($pages as $page) {
            $scanByPage[(int) $page['index']] = (string) $page['scan_image'];
        }

        $decoded = [];
        $crops = [];
        $minEdge = max(28, (int) config('services.document_vision.crop_min_edge', 112));

        try {
            foreach ($fields as $field) {
                if (($field['type'] ?? null) === \App\Forms\FieldType::SIGNATURE) {
                    continue;
                }
                $region = $this->readRegion($field);
                if ($region === null) {
                    continue;
                }

                $page = (int) ($field['page'] ?? 0);
                if (! array_key_exists($page, $decoded)) {
                    $decoded[$page] = $this->decodePage($scanByPage[$page] ?? '');
                }
                if (! $decoded[$page] instanceof \GdImage) {
                    continue;
                }

                $png = $this->cropImage($decoded[$page], $region, $minEdge);
                if ($png === null) {
                    continue;
                }

                $crops[] = [
                    'key' => (string) $field['key'],
                    'label' => (string) ($field['label'] ?? $field['key']),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'options' => $field['options'] ?? null,
                    'image' => base64_encode($png),
                ];
            }
        } finally {
            foreach ($decoded as $image) {
                if ($image instanceof \GdImage) {
                    imagedestroy($image);
                }
            }
        }

        return $crops;
    }

    /**
     * Crop each hand-signed signature from its aligned scan page and store the
     * extracted ink, so the review can pre-fill the signature field with what
     * the signer wrote (the model reports only presence and a box, never an
     * image). Regions come from the schema's known signature bounds — cropped
     * whether or not the model flagged presence — so a signature the small model
     * overlooks is still captured; a blank region yields no ink and is skipped.
     * Each page is decoded once and only the small regions are analysed.
     *
     * @param  array<int,array{index:int,reference_image:string,scan_image:string}>  $pages
     * @param  array<string,array{present:bool,page:int,bounds:?array<int,float>}>  $modelSignatures
     * @return array<string,string> field key => stored signature path
     */
    private function captureSignatures(ManualFormSession|ManualFormSessionDocument $session, array $pages, array $modelSignatures): array
    {
        if (! (bool) config('manual_form.signature_capture.enabled', true)) {
            return [];
        }

        $fields = array_filter(
            (array) ($session->session_schema['extractable_fields'] ?? []),
            // Only crop from an accurate position — the rendered marker
            // ('render') or the deterministic signature cell box ('cell'). A
            // coarse OOXML/vision estimate lands on the wrong cell and extracts
            // printed text as ink.
            fn ($f) => is_array($f)
                && ($f['type'] ?? null) === \App\Forms\FieldType::SIGNATURE
                && in_array($f['bounds_source'] ?? null, ['render', 'cell'], true),
        );
        if ($fields === []) {
            return [];
        }

        $scanByPage = [];
        foreach ($pages as $page) {
            $scanByPage[(int) ($page['index'] ?? 0)] = (string) ($page['scan_image'] ?? '');
        }

        $decoded = [];
        $stored = [];

        try {
            foreach ($fields as $field) {
                $key = (string) ($field['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $page = (int) ($field['page'] ?? ($modelSignatures[$key]['page'] ?? 0));
                $bounds = $this->plausibleBounds($field['bounds'] ?? null)
                    ?? $this->plausibleBounds($modelSignatures[$key]['bounds'] ?? null);
                if ($bounds === null) {
                    continue;
                }

                if (! array_key_exists($page, $decoded)) {
                    $decoded[$page] = $this->decodePage($scanByPage[$page] ?? '');
                }
                $image = $decoded[$page];
                if (! $image instanceof \GdImage) {
                    continue;
                }

                $crop = $this->cropImage($image, $bounds);
                if ($crop === null) {
                    continue;
                }

                $path = SignatureImage::store($crop, 'manual-form/'.($session instanceof ManualFormSessionDocument
                    ? $session->manual_form_session_id.'/documents/'.$session->getKey() : $session->getKey()).'/signatures');
                if ($path !== null) {
                    $stored[$key] = $path;
                }
            }
        } finally {
            foreach ($decoded as $image) {
                if ($image instanceof \GdImage) {
                    imagedestroy($image);
                }
            }
        }

        return $stored;
    }

    /**
     * A `[x1,y1,x2,y2]` box in `[0,1]`, ordered, and not so large it is really
     * the whole page (which would be an unreliable region to crop), else null.
     *
     * @return array<int,float>|null
     */
    private function plausibleBounds(mixed $bounds): ?array
    {
        if (! is_array($bounds) || count($bounds) !== 4) {
            return null;
        }

        $box = array_map(fn ($n) => is_numeric($n) ? (float) $n : null, array_values($bounds));
        if (in_array(null, $box, true)) {
            return null;
        }

        [$x1, $y1, $x2, $y2] = $box;
        foreach ($box as $n) {
            if ($n < 0 || $n > 1) {
                return null;
            }
        }
        if ($x1 >= $x2 || $y1 >= $y2) {
            return null;
        }
        if (($x2 - $x1) > 0.9 && ($y2 - $y1) > 0.9) {
            return null;
        }

        return $box;
    }

    private function decodePage(string $image): \GdImage|false
    {
        if ($image === '') {
            return false;
        }
        $binary = base64_decode($this->bareBase64($image), true);
        if ($binary === false || $binary === '') {
            return false;
        }

        return @imagecreatefromstring($binary);
    }

    /**
     * Crop the normalized region (with a small padding margin) and return PNG
     * bytes, or null when the region is degenerate. With `$minEdge`, a crop
     * whose short side is smaller is upscaled to it (long side capped at
     * 1600px) so a vision model sees small writing at a legible size.
     *
     * @param  array<int,float>  $bounds
     */
    private function cropImage(\GdImage $src, array $bounds, ?int $minEdge = null): ?string
    {
        $width = imagesx($src);
        $height = imagesy($src);

        // A little breathing room so ink at the edge is not clipped and the crop
        // is not entirely ink (which the extractor rejects as an underexposure).
        $padX = ($bounds[2] - $bounds[0]) * 0.06;
        $padY = ($bounds[3] - $bounds[1]) * 0.06;

        $x1 = (int) floor(max(0.0, $bounds[0] - $padX) * $width);
        $y1 = (int) floor(max(0.0, $bounds[1] - $padY) * $height);
        $x2 = (int) ceil(min(1.0, $bounds[2] + $padX) * $width);
        $y2 = (int) ceil(min(1.0, $bounds[3] + $padY) * $height);

        $rectWidth = min($width, $x2) - max(0, $x1);
        $rectHeight = min($height, $y2) - max(0, $y1);
        if ($rectWidth < 2 || $rectHeight < 2) {
            return null;
        }

        $crop = imagecrop($src, [
            'x' => max(0, $x1),
            'y' => max(0, $y1),
            'width' => $rectWidth,
            'height' => $rectHeight,
        ]);
        if ($crop === false) {
            return null;
        }

        if ($minEdge !== null) {
            $short = min($rectWidth, $rectHeight);
            $scale = $short < $minEdge ? $minEdge / $short : 1.0;
            $scale = min($scale, 1600 / max($rectWidth, $rectHeight));
            if ($scale > 1.0) {
                $scaled = imagescale($crop, (int) round($rectWidth * $scale), (int) round($rectHeight * $scale), IMG_BICUBIC);
                if ($scaled !== false) {
                    imagedestroy($crop);
                    $crop = $scaled;
                }
            }
        }

        try {
            ob_start();
            imagepng($crop);
            $bytes = (string) ob_get_clean();

            return $bytes !== '' ? $bytes : null;
        } finally {
            imagedestroy($crop);
        }
    }

    private function bareBase64(string $image): string
    {
        if (str_starts_with($image, 'data:')) {
            $comma = strpos($image, ',');

            return $comma === false ? '' : substr($image, $comma + 1);
        }

        return $image;
    }

    /**
     * @return array<int,array{index:int,base64:string}>
     */
    private function referenceImages(ManualFormSession|ManualFormSessionDocument $session, string $disk): array
    {
        $images = [];
        foreach ((array) $session->page_meta as $page) {
            $path = (string) ($page['path'] ?? '');
            if ($path === '' || ! Storage::disk($disk)->exists($path)) {
                continue;
            }
            $images[(int) ($page['index'] ?? count($images))] = [
                'index' => (int) ($page['index'] ?? 0),
                'base64' => base64_encode((string) Storage::disk($disk)->get($path)),
            ];
        }

        ksort($images);

        return array_values($images);
    }

    /**
     * @return array<int,string> raw scan image bytes in upload order
     */
    private function scanBytes(ManualFormSession|ManualFormSessionDocument $session, string $disk): array
    {
        $storage = Storage::disk($disk);
        $workDir = storage_path('app/manual-scan-'.$session->getKey().'-'.bin2hex(random_bytes(4)));
        $bytes = [];

        try {
            foreach (array_values((array) $session->scan_paths) as $index => $path) {
                $path = (string) $path;
                if ($path === '' || ! $storage->exists($path)) {
                    return [];
                }

                if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
                    $bytes[] = (string) $storage->get($path);

                    continue;
                }

                $raster = $this->rasterizer->rasterize(
                    $storage->path($path),
                    $workDir.'/pdf-'.$index,
                    (int) config('manual_form.render_dpi', 200),
                );
                if (! ($raster['ok'] ?? false)) {
                    return [];
                }

                foreach ((array) ($raster['pages'] ?? []) as $page) {
                    $pagePath = (string) ($page['path'] ?? '');
                    if ($pagePath === '' || ! is_file($pagePath)) {
                        return [];
                    }
                    $bytes[] = (string) File::get($pagePath);
                }
            }

            $maxPages = max(1, (int) config('services.document_vision.max_pages', 10));

            return count($bytes) <= $maxPages ? $bytes : [];
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * The extractable + signature field specs sent to the model.
     *
     * @param  array<int,array<string,mixed>>  $fields
     * @return array<int,array<string,mixed>>
     */
    private function extractionFields(array $fields): array
    {
        return array_map(fn ($f) => [
            'key' => $f['key'],
            'label' => $f['label'] ?? $f['key'],
            'type' => $f['type'] ?? 'text',
            'options' => $f['options'] ?? null,
            'page' => $f['page'] ?? 0,
            'bounds' => $f['bounds'] ?? null,
        ], $fields);
    }

    /**
     * @param  array<int,string>  $warnings
     */
    private function fail(ManualFormSession|ManualFormSessionDocument $session, string $reason, array $warnings = []): void
    {
        $session->forceFill([
            'status' => ManualFormSession::STATUS_FAILED,
            'parse_error' => $reason,
            'parse_warnings' => $warnings ?: $session->parse_warnings,
        ])->save();
        ParseProgress::clear($session);
        if ($session instanceof ManualFormSessionDocument) {
            app(ManualFormSessionService::class)->syncDocuments($session->session);
        }
    }
}
