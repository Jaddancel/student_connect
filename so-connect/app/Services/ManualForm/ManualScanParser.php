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
 * Parses a returned scan against a session's frozen reference pages: aligns the
 * uploaded pages to the reference geometry (OCR sidecar), then asks the vision
 * model to read only the fields that were empty at Start time. Missing,
 * duplicate, or unmatched pages drop the session into a reviewable failure the
 * user can retry from.
 */
class ManualScanParser
{
    public function __construct(
        private readonly OcrClient $ocr,
        private readonly DocumentVisionClient $vision,
        private readonly PdfRasterizer $rasterizer,
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

        $referencePngs = $this->referenceImages($target, $disk);
        $scanBytes = $this->scanBytes($target, $disk);

        if ($referencePngs === [] || $scanBytes === []) {
            $this->fail($target, 'The scan could not be read. Please upload a clearer copy.');

            return;
        }

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

        $result = $this->vision->extract([
            'known_values' => (array) $session->known_fields,
            'fields' => $this->extractionFields($target),
            'pages' => $pages,
        ]);

        if (! ($result['ok'] ?? false)) {
            $this->fail($target, 'The reader is unavailable right now. Your draft is saved — try again shortly.', $warnings);

            return;
        }

        $signatures = (array) ($result['signatures'] ?? []);

        $target->forceFill([
            'status' => ManualFormSession::STATUS_REVIEW,
            'parse_result' => [
                'values' => $result['values'] ?? [],
                'signatures' => $signatures,
                'signature_images' => $this->captureSignatures($target, $pages, $signatures),
                'unresolved' => $result['unresolved'] ?? [],
            ],
            'parse_confidence' => $result['confidence'] ?? [],
            'parse_warnings' => array_values(array_unique(array_merge($warnings, (array) ($result['warnings'] ?? [])))),
            'parse_error' => null,
            'parse_model' => $result['model'] ?? null,
        ])->save();
        if ($document) {
            app(ManualFormSessionService::class)->syncDocuments($session);
        }
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
     * bytes, or null when the region is degenerate.
     *
     * @param  array<int,float>  $bounds
     */
    private function cropImage(\GdImage $src, array $bounds): ?string
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
     * @return array<int,array<string,mixed>>
     */
    private function extractionFields(ManualFormSession|ManualFormSessionDocument $session): array
    {
        $fields = (array) ($session->session_schema['extractable_fields'] ?? []);

        return array_map(fn ($f) => [
            'key' => $f['key'],
            'type' => $f['type'],
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
        if ($session instanceof ManualFormSessionDocument) {
            app(ManualFormSessionService::class)->syncDocuments($session->session);
        }
    }
}
