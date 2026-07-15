<?php

namespace App\Services;

use App\Models\IdTemplate;
use Carbon\Carbon;
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
     * Generational suffixes that belong to the surname, not the given/middle
     * names. Bare "V" is deliberately absent — trailing single letters are far
     * more often middle initials than "the fifth".
     */
    private const NAME_SUFFIXES = ['JR', 'SR', 'II', 'III', 'IV', 'VI'];

    /**
     * Filipino/Hispanic surname particles: a trailing "CRUZ" preceded by any
     * chain of these belongs to one compound surname ("DELA CRUZ",
     * "DELOS SANTOS", "SAN JUAN").
     */
    private const SURNAME_PARTICLES = [
        'DE', 'DEL', 'DELA', 'DELAS', 'DELOS', 'LA', 'LOS',
        'SAN', 'SANTA', 'SANTO', 'STA', 'STO', 'VAN', 'VON', 'DER',
    ];

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
        $fields = $this->normalizeDates($fields);

        return [
            'fields' => $fields,
            'student_id' => $fields['student_id'] ?? null,
            'ok' => true,
        ];
    }

    /**
     * IDs print dates as e.g. `JANUARY 5, 2003`, but every consumer of the
     * scanned value (`<input type="date">` on the signup form, flatpickr on
     * rendered forms) needs `Y-m-d` — anything else is silently discarded by
     * the browser. Normalize the date-typed universal keys.
     *
     * @param  array<string,string>  $fields
     * @return array<string,string>
     */
    private function normalizeDates(array $fields): array
    {
        if (isset($fields['birthday'])) {
            $fields['birthday'] = self::normalizeDate($fields['birthday']) ?? $fields['birthday'];
        }

        return $fields;
    }

    /**
     * Parse the date layouts commonly printed on IDs into `Y-m-d`.
     * Returns null when the text can't be read as a date (caller keeps the
     * raw string, which is no worse than before).
     */
    public static function normalizeDate(string $raw): ?string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $raw), " \t.,");
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // PHP's month-name parsing wants `January`, not `JANUARY`.
        $cased = preg_match('/[A-Za-z]/', $value) ? ucwords(strtolower($value)) : $value;

        $formats = [
            'F j, Y', 'F j Y', 'M j, Y', 'M. j, Y', 'M j Y',
            'n/j/Y', 'n-j-Y', 'Y-n-j', 'Y/n/j', 'j F Y', 'j M Y',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat('!'.$format, $cased);
            } catch (\Throwable) {
                continue;
            }

            if ($parsed !== false && (int) $parsed->year >= 1900 && (int) $parsed->year <= 2100) {
                return $parsed->format('Y-m-d');
            }
        }

        try {
            $parsed = Carbon::parse($cased);

            return ((int) $parsed->year >= 1900 && (int) $parsed->year <= 2100)
                ? $parsed->format('Y-m-d')
                : null;
        } catch (\Throwable) {
            return null;
        }
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
            $parts = self::splitFullName((string) ($fields['full_name'] ?? $fields['name']));
            unset($fields['full_name'], $fields['name']);
            foreach ($parts as $key => $value) {
                if (($fields[$key] ?? '') === '') {
                    $fields[$key] = $value;
                }
            }
        } elseif (isset($fields['first_name']) && str_contains((string) $fields['first_name'], ',')) {
            // The comma-form was mis-captured into first_name; replace it wholesale.
            foreach (self::splitFullName((string) $fields['first_name']) as $key => $value) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    /**
     * Parse a full name into its parts.
     *
     * Comma form `LAST, FIRST [MIDDLE-INITIAL] [SUFFIX]`: everything before the
     * comma is the surname; after it, only a trailing initial is treated as the
     * middle name — the rest is the (possibly compound) first name, so
     * "DELA CRUZ, JUAN MIGUEL P." → first "JUAN MIGUEL", middle "P.".
     *
     * No-comma form `FIRST [MIDDLE-INITIAL] LAST [SUFFIX]`: the surname is the
     * final token plus any preceding particle chain ("JUAN MIGUEL DELA CRUZ" →
     * last "DELA CRUZ"). Generational suffixes stay with the surname.
     *
     * Full-word middle names are indistinguishable from compound first names on
     * a printed ID, so they intentionally stay in first_name.
     *
     * @return array<string,string>  subset of first_name/middle_name/last_name
     */
    public static function splitFullName(string $raw): array
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $raw));
        if ($value === '') {
            return [];
        }

        if (str_contains($value, ',')) {
            [$last, $rest] = array_pad(explode(',', $value, 2), 2, '');
            [$lastTokens, $lastSuffix] = self::stripSuffix(self::tokenize($last));
            [$tokens, $restSuffix] = self::stripSuffix(self::tokenize($rest));
            $suffix = $lastSuffix !== '' ? $lastSuffix : $restSuffix;

            $middle = '';
            if (count($tokens) >= 2 && self::isInitial(end($tokens))) {
                $middle = array_pop($tokens);
            }

            return array_filter([
                'first_name' => implode(' ', $tokens),
                'middle_name' => $middle,
                'last_name' => trim(implode(' ', $lastTokens).($suffix !== '' ? ' '.$suffix : '')),
            ], static fn ($v) => $v !== '');
        }

        [$tokens, $suffix] = self::stripSuffix(self::tokenize($value));
        if ($tokens === []) {
            return [];
        }
        if (count($tokens) === 1) {
            return ['first_name' => $tokens[0]];
        }

        $lastParts = [array_pop($tokens)];
        while (count($tokens) > 1 && self::isSurnameParticle(end($tokens))) {
            array_unshift($lastParts, array_pop($tokens));
        }

        $middle = '';
        if (count($tokens) >= 2 && self::isInitial(end($tokens))) {
            $middle = array_pop($tokens);
        }

        return array_filter([
            'first_name' => implode(' ', $tokens),
            'middle_name' => $middle,
            'last_name' => trim(implode(' ', $lastParts).($suffix !== '' ? ' '.$suffix : '')),
        ], static fn ($v) => $v !== '');
    }

    /**
     * @return array<int,string>
     */
    private static function tokenize(string $value): array
    {
        return array_values(array_filter(explode(' ', trim($value)), static fn ($t) => $t !== ''));
    }

    /**
     * Split a trailing generational suffix off a token list.
     *
     * @param  array<int,string>  $tokens
     * @return array{0: array<int,string>, 1: string}  [remaining tokens, suffix ('' when none)]
     */
    private static function stripSuffix(array $tokens): array
    {
        if (count($tokens) >= 2 && in_array(strtoupper(rtrim((string) end($tokens), '.')), self::NAME_SUFFIXES, true)) {
            $suffix = array_pop($tokens);

            return [$tokens, $suffix];
        }

        return [$tokens, ''];
    }

    private static function isInitial(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z]\.?$/', $token);
    }

    private static function isSurnameParticle(string $token): bool
    {
        return in_array(strtoupper(rtrim($token, '.')), self::SURNAME_PARTICLES, true);
    }

    /**
     * @return array{fields: array<string,string>, images: array<string,string>, student_id: ?string, ok: bool, note: string}
     */
    private function empty(string $note): array
    {
        return ['fields' => [], 'images' => [], 'student_id' => null, 'ok' => false, 'note' => $note];
    }
}
