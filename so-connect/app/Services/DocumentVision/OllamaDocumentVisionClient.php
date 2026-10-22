<?php

namespace App\Services\DocumentVision;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ollama implementation of {@see DocumentVisionClient}, targeting a
 * `qwen2.5vl` model via `/api/chat` with `stream:false`, `format:json`, and
 * `temperature:0`. Images are passed base64-encoded on the message.
 *
 * Extraction reads each cropped field on its own (`crops`), which a small
 * vision model does far more accurately than a whole page, and falls back to a
 * whole-page read for fields without a crop or whose crop call failed.
 *
 * Every failure (missing config, timeout, non-200, malformed JSON) yields a
 * structured `ok:false` envelope with a closed-set status; raw model output is
 * validated through {@see DocumentVisionResultNormalizer} before it is returned.
 */
class OllamaDocumentVisionClient implements DocumentVisionClient
{
    public function __construct(private readonly DocumentVisionResultNormalizer $normalizer) {}

    public function extract(array $request): array
    {
        $fields = array_values(array_filter($request['fields'] ?? [], 'is_array'));
        $pages = array_values(array_filter($request['pages'] ?? [], 'is_array'));
        $knownValues = (array) ($request['known_values'] ?? []);

        if ($fields === [] || $pages === []) {
            return ['ok' => false, 'status' => 'invalid_output', 'error' => 'no fields or pages to extract'];
        }

        $fieldsByKey = [];
        foreach ($fields as $field) {
            $fieldsByKey[(string) ($field['key'] ?? '')] = $field;
        }
        $crops = array_values(array_filter(
            $request['crops'] ?? [],
            fn ($c) => is_array($c)
                && isset($fieldsByKey[(string) ($c['key'] ?? '')])
                && ! array_key_exists((string) $c['key'], $knownValues)
                && is_string($c['image'] ?? null) && $c['image'] !== '',
        ));

        $merged = ['values' => [], 'signatures' => [], 'confidence' => [], 'unresolved' => [], 'warnings' => [], 'sources' => []];
        $anyOk = false;
        $lastFailure = null;
        $readByCrop = [];

        $batches = array_chunk($crops, max(1, (int) config('services.document_vision.crops_per_request', 1)));
        $croppedKeys = array_fill_keys(array_map(fn ($c) => (string) $c['key'], $crops), true);
        $needsPage = array_filter($fields, fn ($f) => ! isset($croppedKeys[(string) ($f['key'] ?? '')])) !== [];
        $totalCalls = count($batches) + ($needsPage ? 1 : 0);
        $done = 0;

        foreach ($batches as $batch) {
            $this->reportProgress($request, $done, $totalCalls, count($batch) === 1
                ? 'Reading "'.($batch[0]['label'] ?? $batch[0]['key']).'" ('.($done + 1).' of '.$totalCalls.')…'
                : 'Reading fields ('.($done + 1).' of '.$totalCalls.')…');
            $result = $this->call(
                $this->cropSystemPrompt(),
                $this->cropPrompt($batch),
                array_map(fn ($c) => $this->bareBase64((string) $c['image']), $batch),
            );
            $done++;
            if (! $result['ok']) {
                $lastFailure = $result;
                if (in_array($result['status'] ?? null, ['unreachable', 'unavailable'], true)) {
                    break;
                }

                continue;
            }

            $anyOk = true;
            $batchFields = array_map(fn ($c) => $fieldsByKey[(string) $c['key']], $batch);
            $json = $result['json'];
            if (count($batch) === 1 && ! isset($json['fields']) && array_key_exists('value', $json)) {
                $json = ['fields' => [(string) $batch[0]['key'] => $json]];
            }
            $this->mergeInto($merged, $this->normalizer->normalize($batchFields, $json, $knownValues), 'crop');
            foreach ($batch as $crop) {
                $readByCrop[(string) $crop['key']] = true;
            }
        }

        $pageFields = array_values(array_filter($fields, fn ($f) => ! isset($readByCrop[(string) ($f['key'] ?? '')])));
        if ($pageFields !== [] && ! in_array($lastFailure['status'] ?? null, ['unreachable', 'unavailable'], true)) {
            $images = [];
            foreach ($pages as $page) {
                // Alignment has already warped the scan onto the frozen reference
                // geometry. Sending the reference too doubles visual tokens and
                // can exceed the model context or timeout without adding content
                // the handwriting extractor needs.
                if (is_string($page['scan_image'] ?? null) && $page['scan_image'] !== '') {
                    $images[] = $this->fitToMaxEdge($this->bareBase64($page['scan_image']));
                }
            }

            if ($images === []) {
                return ['ok' => false, 'status' => 'invalid_output', 'error' => 'no page images provided'];
            }

            $totalCalls = max($totalCalls, $done + 1);
            $this->reportProgress($request, $done, $totalCalls, 'Reading the rest of the page ('.($done + 1).' of '.$totalCalls.')…');
            $result = $this->call($this->extractionSystemPrompt(), $this->extractionPrompt($pageFields, $knownValues, $pages), $images);
            if ($result['ok']) {
                $anyOk = true;
                $this->mergeInto($merged, $this->normalizer->normalize($pageFields, $result['json'], $knownValues), 'page');
            } else {
                $lastFailure = $result;
                foreach ($pageFields as $field) {
                    if (($field['type'] ?? null) !== 'signature' && ! array_key_exists($field['key'], $knownValues)) {
                        $merged['unresolved'][] = (string) $field['key'];
                    }
                }
                $merged['warnings'][] = 'Some fields could not be read from the full page; fill them in yourself.';
            }
        }

        if (! $anyOk) {
            return $lastFailure ?? ['ok' => false, 'status' => 'invalid_output', 'error' => 'nothing to extract'];
        }

        return [
            'ok' => true,
            'model' => $this->model(),
            'values' => $merged['values'],
            'signatures' => $merged['signatures'],
            'confidence' => $merged['confidence'],
            'unresolved' => array_values(array_unique($merged['unresolved'])),
            'warnings' => array_values(array_unique($merged['warnings'])),
            'sources' => $merged['sources'],
        ];
    }

    /**
     * Tell the caller how far extraction has got (`$request['progress']`, an
     * optional `fn(int $done, int $total, string $label)`). Never throws.
     *
     * @param  array<string,mixed>  $request
     */
    private function reportProgress(array $request, int $done, int $total, string $label): void
    {
        if (! is_callable($request['progress'] ?? null)) {
            return;
        }

        try {
            ($request['progress'])($done, max(1, $total), $label);
        } catch (\Throwable) {
        }
    }

    /**
     * @param  array<string,array<mixed>>  $merged
     * @param  array<string,array<mixed>>  $normalized
     */
    private function mergeInto(array &$merged, array $normalized, string $source): void
    {
        foreach ($normalized['values'] as $key => $value) {
            $merged['values'][$key] = $value;
            $merged['sources'][$key] = $source;
        }
        $merged['signatures'] = array_merge($merged['signatures'], $normalized['signatures']);
        $merged['confidence'] = array_merge($merged['confidence'], $normalized['confidence']);
        $merged['unresolved'] = array_merge($merged['unresolved'], $normalized['unresolved']);
        $merged['warnings'] = array_merge($merged['warnings'], $normalized['warnings']);
    }

    /**
     * Downscale a page so its longest edge fits DOCUMENT_VISION_MAX_IMAGE_EDGE,
     * bounding the visual tokens a page costs. Undecodable or already-small
     * images pass through untouched.
     */
    private function fitToMaxEdge(string $base64): string
    {
        $maxEdge = (int) config('services.document_vision.max_image_edge', 1600);
        $binary = $maxEdge > 0 ? base64_decode($base64, true) : false;
        $image = $binary ? @imagecreatefromstring($binary) : false;
        if (! $image instanceof \GdImage) {
            return $base64;
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            if (max($width, $height) <= $maxEdge) {
                return $base64;
            }
            $scale = $maxEdge / max($width, $height);
            $scaled = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC);
            if ($scaled === false) {
                return $base64;
            }
            ob_start();
            imagepng($scaled);
            $bytes = (string) ob_get_clean();
            imagedestroy($scaled);

            return $bytes !== '' ? base64_encode($bytes) : $base64;
        } finally {
            imagedestroy($image);
        }
    }

    public function locateWritableAreas(array $request): array
    {
        $fields = array_values(array_filter($request['fields'] ?? [], 'is_array'));
        $pages = array_values(array_filter($request['pages'] ?? [], 'is_array'));

        if ($fields === [] || $pages === []) {
            return ['ok' => false, 'status' => 'invalid_output', 'error' => 'no fields or pages provided'];
        }

        $images = [];
        foreach ($pages as $page) {
            if (is_string($page['image'] ?? null) && $page['image'] !== '') {
                $images[] = $this->bareBase64($page['image']);
            }
        }

        if ($images === []) {
            return ['ok' => false, 'status' => 'invalid_output', 'error' => 'no page images provided'];
        }

        $prompt = $this->locatePrompt($fields, $pages);

        $result = $this->call($this->locateSystemPrompt(), $prompt, $images);
        if (! $result['ok']) {
            return $result;
        }

        return [
            'ok' => true,
            'model' => $this->model(),
            'areas' => $this->normalizeAreas($fields, $result['json']),
            'warnings' => [],
        ];
    }

    public function healthy(): bool
    {
        $base = $this->baseUrl();
        if ($base === '') {
            return false;
        }

        try {
            $response = Http::timeout(5)->get($base.'/api/tags');
            if (! $response->successful()) {
                return false;
            }

            $models = collect($response->json('models', []))
                ->pluck('name')
                ->map(fn ($n) => (string) $n);

            return $models->contains($this->model())
                || $models->contains(fn ($n) => str_starts_with($n, explode(':', $this->model())[0]));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Perform the `/api/chat` call and decode strict JSON.
     *
     * @param  array<int,string>  $images  base64-encoded page images
     * @return array{ok:bool, json?:array<string,mixed>, status?:string, error?:string}
     */
    private function call(string $system, string $prompt, array $images): array
    {
        $base = $this->baseUrl();
        if ($base === '') {
            return ['ok' => false, 'status' => 'unavailable', 'error' => 'document vision not configured'];
        }

        $timeout = (int) config('services.document_vision.timeout', 180);

        try {
            $response = Http::timeout($timeout)->post($base.'/api/chat', [
                'model' => $this->model(),
                'stream' => false,
                'format' => 'json',
                'options' => [
                    'temperature' => 0,
                    'num_ctx' => max(4096, (int) config('services.document_vision.context_length', 8192)),
                ],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt, 'images' => $images],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Document vision unreachable: '.$e->getMessage());

            return ['ok' => false, 'status' => 'unreachable', 'error' => 'document vision unreachable'];
        }

        if ($response->status() === 408 || $response->status() === 504) {
            return ['ok' => false, 'status' => 'timeout', 'error' => 'document vision timed out'];
        }

        if (! $response->successful()) {
            Log::warning('Document vision returned non-200', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return ['ok' => false, 'status' => 'http_error', 'error' => 'document vision error'];
        }

        $content = $response->json('message.content');
        if (! is_string($content) || trim($content) === '') {
            return ['ok' => false, 'status' => 'invalid_output', 'error' => 'empty model response'];
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            Log::warning('Document vision returned non-JSON content');

            return ['ok' => false, 'status' => 'invalid_output', 'error' => 'malformed model response'];
        }

        return ['ok' => true, 'json' => $decoded];
    }

    /**
     * @param  array<int,array{key:string,label:string,type:string}>  $fields
     * @param  array<string,mixed>  $raw
     * @return array<string,array{page:int,bounds:?array<int,float>,writable_area:string}>
     */
    private function normalizeAreas(array $fields, array $raw): array
    {
        $rawAreas = is_array($raw['fields'] ?? null) ? $raw['fields'] : $raw;
        $areas = [];

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $entry = is_array($rawAreas[$key] ?? null) ? $rawAreas[$key] : null;
            $bounds = $this->boundsFrom($entry['bounds'] ?? null);

            $status = $bounds === null ? 'missing' : (string) ($entry['writable_area'] ?? 'present');
            if (! in_array($status, ['present', 'missing', 'uncertain'], true)) {
                $status = $bounds === null ? 'missing' : 'present';
            }

            $areas[$key] = [
                'page' => (int) ($entry['page'] ?? 0),
                'bounds' => $bounds,
                'writable_area' => $status,
            ];
        }

        return $areas;
    }

    /**
     * @return array<int,float>|null
     */
    private function boundsFrom(mixed $bounds): ?array
    {
        if (! is_array($bounds) || count($bounds) !== 4) {
            return null;
        }

        $box = array_map(fn ($n) => is_numeric($n) ? (float) $n : null, array_values($bounds));
        if (in_array(null, $box, true)) {
            return null;
        }
        foreach ($box as $n) {
            if ($n < 0 || $n > 1) {
                return null;
            }
        }
        [$x1, $y1, $x2, $y2] = $box;

        return ($x1 <= $x2 && $y1 <= $y2) ? $box : null;
    }

    private function extractionSystemPrompt(): string
    {
        return implode(' ', [
            'You read the answers a person filled into a scanned printed form (handwritten or typed) and return strict JSON only.',
            'Extract only the fields listed by the user, identified by their label. Ignore everything else.',
            'The form\'s own printed labels, lines and table borders are not answers; read only what the person added.',
            'Never overwrite or re-read any key in known_values; those were printed, not handwritten.',
            'Return null for any field you cannot read confidently. Never infer, guess, or complete a value.',
            'Copy text exactly as written, keeping spelling, digits and capitalization.',
            'For select, radio, or checkbox fields, return the option value whose label best matches the answer, or null if none matches.',
            'Write dates as YYYY-MM-DD and times as HH:MM (24-hour).',
            'For signature fields, report only presence and a bounding box, never text.',
            'Report a confidence between 0 and 1 per field.',
            'Bounding boxes are [x1,y1,x2,y2] fractions of the page width and height, origin top-left.',
        ]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $fields
     * @param  array<string,mixed>  $knownValues
     * @param  array<int,array<string,mixed>>  $pages
     */
    private function extractionPrompt(array $fields, array $knownValues, array $pages): string
    {
        $spec = array_map(fn (array $f) => array_filter([
            'key' => $f['key'] ?? null,
            'label' => $f['label'] ?? null,
            'type' => $f['type'] ?? null,
            'options' => $f['options'] ?? null,
            'page' => $f['page'] ?? 0,
            'bounds' => $f['bounds'] ?? null,
        ], fn ($v) => $v !== null), $fields);

        return 'The images are the scanned pages in order; image 1 is page 0. '
            .'Each field\'s "bounds" marks where its answer area is on its page (approximate; the answer may sit slightly below). '
            .'known_values (already printed, do not change): '.json_encode($knownValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' '
            .'fields to extract: '.json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' '
            .'Respond with JSON: {"fields":{"<key>":{"value":<string|null>,"confidence":<0-1>,"page":<int>,"bounds":[x1,y1,x2,y2]}},"warnings":[...]}. '
            .'For signature keys use {"present":<bool>,"confidence":<0-1>,"page":<int>,"bounds":[...]} instead of value.';
    }

    private function cropSystemPrompt(): string
    {
        return implode(' ', [
            'You read one answer from a cropped image of a filled-in printed form and return strict JSON only.',
            'Each image shows the area where a person wrote or typed the answer to the named field.',
            'The crop may include fragments of the form\'s printed labels, lines or table borders; ignore them and read only the answer the person added.',
            'Return null if the area is blank or the answer is unreadable. Never infer, guess, or complete a value.',
            'Copy text exactly as written, keeping spelling, digits and capitalization.',
            'For select, radio, or checkbox fields, return the option value whose label best matches the answer, or null if none matches.',
            'Write dates as YYYY-MM-DD and times as HH:MM (24-hour).',
            'Report a confidence between 0 and 1.',
        ]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $batch
     */
    private function cropPrompt(array $batch): string
    {
        $spec = array_map(fn (array $c) => array_filter([
            'key' => $c['key'] ?? null,
            'label' => $c['label'] ?? null,
            'type' => $c['type'] ?? null,
            'options' => $c['options'] ?? null,
        ], fn ($v) => $v !== null), $batch);

        $intro = count($batch) === 1
            ? 'The image is the answer area for this field: '
            : 'Image N is the answer area for field N in this list: ';

        return $intro.json_encode(count($batch) === 1 ? $spec[0] : $spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' '
            .'Respond with JSON: {"fields":{"<key>":{"value":<string|null>,"confidence":<0-1>}}}.';
    }

    private function locateSystemPrompt(): string
    {
        return implode(' ', [
            'You locate the blank writable area for each named field on a blank printed form and return strict JSON only.',
            'Bounding boxes are [x1,y1,x2,y2] normalized to [0,1] with origin top-left.',
            'If a field has no visible blank area to write in, mark it missing.',
        ]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $fields
     * @param  array<int,array<string,mixed>>  $pages
     */
    private function locatePrompt(array $fields, array $pages): string
    {
        $spec = array_map(fn (array $f) => [
            'key' => $f['key'] ?? null,
            'label' => $f['label'] ?? null,
            'type' => $f['type'] ?? null,
        ], $fields);

        return 'Locate the writable area for each field on the blank form pages (0-indexed). '
            .'fields: '.json_encode($spec, JSON_UNESCAPED_SLASHES).' '
            .'Respond with JSON: {"fields":{"<key>":{"page":<int>,"bounds":[x1,y1,x2,y2],"writable_area":"present|missing|uncertain"}}}.';
    }

    private function model(): string
    {
        return (string) config('services.document_vision.model', 'qwen2.5vl:7b');
    }

    private function baseUrl(): string
    {
        if ((string) config('services.document_vision.provider') !== 'ollama') {
            return '';
        }

        return rtrim((string) config('services.document_vision.url', ''), '/');
    }

    private function bareBase64(string $image): string
    {
        return (string) preg_replace('/^data:image\/[^;]+;base64,/', '', $image);
    }
}
