<?php

namespace App\Services;

use App\Models\IdTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the PaddleOCR sidecar (docker/ocr). Sends a student's ID
 * photo plus a template's frozen zone payload and maps the returned per-zone
 * text back onto each zone's destination `field`.
 *
 * Failure is always graceful: a timeout, non-200, or unreachable sidecar yields
 * an empty result (never an exception into the request flow).
 */
class OcrClient
{
    /**
     * @return array{fields: array<string,string>, student_id: ?string, ok: bool, note?: string}
     */
    public function scan(IdTemplate $template, UploadedFile|string $photo, string $side = 'front'): array
    {
        $url = rtrim((string) config('services.ocr.url'), '/').'/scan';
        $timeout = (int) config('services.ocr.timeout', 60);

        try {
            $request = Http::timeout($timeout);

            if ($photo instanceof UploadedFile) {
                $request = $request->attach(
                    'image',
                    file_get_contents($photo->getRealPath()),
                    $photo->getClientOriginalName() ?: 'id.jpg',
                );
            } else {
                $request = $request->attach('image', file_get_contents($photo), basename($photo));
            }

            $response = $request->post($url, [
                'template' => json_encode($template->toScannerPayload($side)),
            ]);

            if (! $response->successful()) {
                Log::warning('OCR sidecar returned non-200', ['status' => $response->status()]);

                return $this->empty('scanner error');
            }

            $result = $this->mapFields($template, (array) $response->json('fields', []), $side);
            $result['images'] = $this->mapImages($template, (array) $response->json('images', []), $side);

            return $result;
        } catch (\Throwable $e) {
            Log::warning('OCR sidecar unreachable: '.$e->getMessage());

            return $this->empty('scanner unavailable');
        }
    }

    /**
     * Ask the sidecar which stored signature (if any) a freshly-drawn one
     * matches. Candidates are [{id, image}] with base64-encoded image bytes.
     *
     * @param  array<int,array{id:int|string, image:string}>  $candidates
     * @return array{ok: bool, match: bool, best: ?array{id: int|string, score: float}, note?: string}
     */
    public function identifySignature(string $probePng, array $candidates): array
    {
        $url = rtrim((string) config('services.ocr.url'), '/').'/signature-identify';
        $timeout = (int) config('services.ocr.timeout', 60);

        try {
            $response = Http::timeout($timeout)
                ->attach('probe', $probePng, 'probe.png')
                ->post($url, ['candidates' => json_encode($candidates)]);

            if (! $response->successful()) {
                Log::warning('Signature identify returned non-200', ['status' => $response->status()]);

                return ['ok' => false, 'match' => false, 'best' => null, 'note' => 'scanner error'];
            }

            return [
                'ok' => true,
                'match' => (bool) $response->json('match', false),
                'best' => $response->json('best'),
            ];
        } catch (\Throwable $e) {
            Log::warning('Signature identify unreachable: '.$e->getMessage());

            return ['ok' => false, 'match' => false, 'best' => null, 'note' => 'scanner unavailable'];
        }
    }

    /**
     * Map the sidecar's zone-name → image-crop results (signature zones) onto
     * each zone's destination `field`, mirroring mapFields().
     *
     * @param  array<string,mixed>  $raw
     * @return array<string,string>
     */
    private function mapImages(IdTemplate $template, array $raw, string $side = 'front'): array
    {
        $images = [];
        foreach ($template->zonesForSide($side) as $zone) {
            $name = $zone['name'] ?? null;
            $field = $zone['field'] ?? $name;
            if (! $name || ! $field) {
                continue;
            }
            if (is_string($raw[$name] ?? null) && str_starts_with($raw[$name], 'data:image')) {
                $images[$field] = $raw[$name];
            }
        }

        return $images;
    }

    /**
     * Map the sidecar's zone-name → text results onto each zone's `field`,
     * exposing `student_id` explicitly.
     *
     * @param  array<string,mixed>  $raw
     * @return array{fields: array<string,string>, student_id: ?string, ok: bool}
     */
    private function mapFields(IdTemplate $template, array $raw, string $side = 'front'): array
    {
        $fields = [];
        foreach ($template->zonesForSide($side) as $zone) {
            $name = $zone['name'] ?? null;
            $field = $zone['field'] ?? $name;
            if (! $name || ! $field) {
                continue;
            }
            if (array_key_exists($name, $raw) && $raw[$name] !== null && $raw[$name] !== '') {
                $fields[$field] = (string) $raw[$name];
            }
        }

        $fields = $this->expandName($fields);

        return [
            'fields' => $fields,
            'student_id' => $fields['student_id'] ?? null,
            'ok' => true,
        ];
    }

    /**
     * PH IDs print the name as `LAST, FIRST MIDDLE`. When a template captures the
     * whole name into a single zone — either a dedicated `full_name`/`name` field
     * or a `first_name` zone that actually swallowed the comma-form — split it
     * into the canonical `first_name` / `middle_name` / `last_name` keys the
     * signup form understands. Explicit per-part zones are never overwritten.
     *
     * @param  array<string,string>  $fields
     * @return array<string,string>
     */
    private function expandName(array $fields): array
    {
        if (isset($fields['full_name']) || isset($fields['name'])) {
            $parts = $this->splitFullName((string) ($fields['full_name'] ?? $fields['name']));
            unset($fields['full_name'], $fields['name']);
            foreach ($parts as $key => $value) {
                if (($fields[$key] ?? '') === '') {
                    $fields[$key] = $value;
                }
            }
        } elseif (isset($fields['first_name']) && str_contains((string) $fields['first_name'], ',')) {
            // The comma-form was mis-captured into first_name; replace it wholesale.
            foreach ($this->splitFullName((string) $fields['first_name']) as $key => $value) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    /**
     * Parse a full name into its parts. Handles the canonical
     * `LAST, FIRST MIDDLE...` form and falls back to `FIRST MIDDLE LAST` when no
     * comma is present.
     *
     * @return array<string,string>  subset of first_name/middle_name/last_name
     */
    private function splitFullName(string $raw): array
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $raw));
        if ($value === '') {
            return [];
        }

        if (str_contains($value, ',')) {
            [$last, $rest] = array_pad(explode(',', $value, 2), 2, '');
            $tokens = array_values(array_filter(explode(' ', trim($rest)), static fn ($t) => $t !== ''));

            return array_filter([
                'last_name' => trim($last),
                'first_name' => $tokens[0] ?? '',
                'middle_name' => implode(' ', array_slice($tokens, 1)),
            ], static fn ($v) => $v !== '');
        }

        $tokens = array_values(array_filter(explode(' ', $value), static fn ($t) => $t !== ''));
        if (count($tokens) === 1) {
            return ['first_name' => $tokens[0]];
        }

        $first = array_shift($tokens);
        $last = array_pop($tokens);

        return array_filter([
            'first_name' => $first,
            'middle_name' => implode(' ', $tokens),
            'last_name' => $last,
        ], static fn ($v) => $v !== '');
    }

    /**
     * @return array{fields: array<string,string>, images: array<string,string>, student_id: ?string, ok: bool, note: string}
     */
    private function empty(string $note): array
    {
        return ['fields' => [], 'images' => [], 'student_id' => null, 'ok' => false, 'note' => $note];
    }
}
