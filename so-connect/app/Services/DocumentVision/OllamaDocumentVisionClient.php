<?php

namespace App\Services\DocumentVision;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ollama implementation of {@see DocumentVisionClient}, targeting a host-native
 * `qwen2.5vl:3b` model via `/api/chat` with `stream:false`, `format:json`, and
 * `temperature:0`. Images are passed base64-encoded on the message.
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

        $images = [];
        foreach ($pages as $page) {
            foreach (['reference_image', 'scan_image'] as $imageKey) {
                if (is_string($page[$imageKey] ?? null) && $page[$imageKey] !== '') {
                    $images[] = $page[$imageKey];
                }
            }
        }

        if ($images === []) {
            return ['ok' => false, 'status' => 'invalid_output', 'error' => 'no page images provided'];
        }

        $prompt = $this->extractionPrompt($fields, $knownValues, $pages);

        $result = $this->call($this->extractionSystemPrompt(), $prompt, $images);
        if (! $result['ok']) {
            return $result;
        }

        $normalized = $this->normalizer->normalize($fields, $result['json'], $knownValues);

        return [
            'ok' => true,
            'model' => $this->model(),
            'values' => $normalized['values'],
            'signatures' => $normalized['signatures'],
            'confidence' => $normalized['confidence'],
            'unresolved' => $normalized['unresolved'],
            'warnings' => $normalized['warnings'],
        ];
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
                $images[] = $page['image'];
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
                'options' => ['temperature' => 0],
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
            Log::warning('Document vision returned non-200', ['status' => $response->status()]);

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
            'You read handwriting from a scanned printed form and return strict JSON only.',
            'Extract only the fields listed by the user. Ignore everything else.',
            'Never overwrite or re-read any key in known_values; those were printed, not handwritten.',
            'Return null for any field you cannot read confidently. Never infer, guess, or complete a value.',
            'For select, radio, or checkbox fields, return the option value whose label best matches the handwriting, or null if none matches.',
            'For signature fields, report only presence and a bounding box, never text.',
            'Report a confidence between 0 and 1 per field.',
            'Bounding boxes are [x1,y1,x2,y2] normalized to [0,1] with origin top-left.',
        ]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $fields
     * @param  array<string,mixed>  $knownValues
     * @param  array<int,array<string,mixed>>  $pages
     */
    private function extractionPrompt(array $fields, array $knownValues, array $pages): string
    {
        $spec = array_map(fn (array $f) => [
            'key' => $f['key'] ?? null,
            'type' => $f['type'] ?? null,
            'options' => $f['options'] ?? null,
            'page' => $f['page'] ?? 0,
            'bounds' => $f['bounds'] ?? null,
        ], $fields);

        return 'Read the handwritten answers on the scanned pages. '
            .'The images alternate reference page then scan page, in order. '
            .'known_values (already printed, do not change): '.json_encode($knownValues, JSON_UNESCAPED_SLASHES).' '
            .'fields to extract: '.json_encode($spec, JSON_UNESCAPED_SLASHES).' '
            .'Respond with JSON: {"fields":{"<key>":{"value":<string|null>,"confidence":<0-1>,"page":<int>,"bounds":[x1,y1,x2,y2]}},"warnings":[...]}. '
            .'For signature keys use {"present":<bool>,"confidence":<0-1>,"page":<int>,"bounds":[...]} instead of value.';
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
        return (string) config('services.document_vision.model', 'qwen2.5vl:3b');
    }

    private function baseUrl(): string
    {
        if ((string) config('services.document_vision.provider') !== 'ollama') {
            return '';
        }

        return rtrim((string) config('services.document_vision.url', ''), '/');
    }
}
