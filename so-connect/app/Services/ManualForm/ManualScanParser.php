<?php

namespace App\Services\ManualForm;

use App\Models\ManualFormSession;
use App\Services\DocumentVision\DocumentVisionClient;
use App\Services\OcrClient;
use App\Services\PdfRasterizer;
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

    public function parse(string $sessionId): void
    {
        $session = ManualFormSession::find($sessionId);
        if ($session === null || $session->status !== ManualFormSession::STATUS_PARSING) {
            return;
        }

        $disk = (string) config('documents.disk', 'public');

        $referencePngs = $this->referenceImages($session, $disk);
        $scanBytes = $this->scanBytes($session, $disk);

        if ($referencePngs === [] || $scanBytes === []) {
            $this->fail($session, 'The scan could not be read. Please upload a clearer copy.');

            return;
        }

        $alignment = $this->ocr->alignPages(array_column($referencePngs, 'base64'), $scanBytes);
        if (! ($alignment['ok'] ?? false)) {
            $this->fail($session, 'The scan pages could not be aligned. Please try again.');

            return;
        }

        $alignedPages = (array) ($alignment['pages'] ?? []);
        $warnings = (array) ($alignment['warnings'] ?? []);

        $unmatched = array_values(array_filter($alignedPages, fn ($p) => ! ($p['matched'] ?? false)));
        if ($unmatched !== [] || $alignedPages === []) {
            $this->fail(
                $session,
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
            $this->fail($session, 'No usable pages were produced from the scan.', $warnings);

            return;
        }

        $result = $this->vision->extract([
            'known_values' => (array) $session->known_fields,
            'fields' => $this->extractionFields($session),
            'pages' => $pages,
        ]);

        if (! ($result['ok'] ?? false)) {
            $this->fail($session, 'The reader is unavailable right now. Your draft is saved — try again shortly.', $warnings);

            return;
        }

        $session->forceFill([
            'status' => ManualFormSession::STATUS_REVIEW,
            'parse_result' => [
                'values' => $result['values'] ?? [],
                'signatures' => $result['signatures'] ?? [],
                'unresolved' => $result['unresolved'] ?? [],
            ],
            'parse_confidence' => $result['confidence'] ?? [],
            'parse_warnings' => array_values(array_unique(array_merge($warnings, (array) ($result['warnings'] ?? [])))),
            'parse_error' => null,
            'parse_model' => $result['model'] ?? null,
        ])->save();
    }

    /**
     * @return array<int,array{index:int,base64:string}>
     */
    private function referenceImages(ManualFormSession $session, string $disk): array
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
    private function scanBytes(ManualFormSession $session, string $disk): array
    {
        $storage = Storage::disk($disk);
        $workDir = storage_path('app/tmp/manual-scan-'.$session->getKey().'-'.bin2hex(random_bytes(4)));
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
    private function extractionFields(ManualFormSession $session): array
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
    private function fail(ManualFormSession $session, string $reason, array $warnings = []): void
    {
        $session->forceFill([
            'status' => ManualFormSession::STATUS_FAILED,
            'parse_error' => $reason,
            'parse_warnings' => $warnings ?: $session->parse_warnings,
        ])->save();
    }
}
