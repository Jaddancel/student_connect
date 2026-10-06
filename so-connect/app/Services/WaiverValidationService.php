<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Fuzzy-matches a scanned waiver's extracted details against the event's
 * expected details (product decision #7): case/whitespace/date/time are
 * normalized, names are compared by similarity, dates/times by normalized
 * equality, all against tunable thresholds (config/waiver.php). Also enforces
 * required stamp / signature presence.
 */
class WaiverValidationService
{
    public function normalizeText(string $value): string
    {
        // Lowercase, strip punctuation, collapse whitespace.
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    public function normalizeDate(string $value): ?string
    {
        try {
            return Carbon::parse(trim($value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function normalizeTime(string $value): ?string
    {
        try {
            return Carbon::parse(trim($value))->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Similarity in [0,1] between two already-normalized strings
     * (0 when either is empty).
     */
    public function similarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        $distance = levenshtein(
            mb_substr($a, 0, 255),
            mb_substr($b, 0, 255),
        );
        $max = max(mb_strlen($a), mb_strlen($b));

        return $max === 0 ? 0.0 : max(0.0, 1.0 - ($distance / $max));
    }

    /**
     * @param  array<string,string>  $extracted  field => scanned value
     * @param  array<string,array{value:string, type?:string}>  $expected  field => expectation
     * @param  array{stamp?:bool, signature?:bool}  $flags  sidecar presence detections
     * @return array{valid:bool, fields:array<string,array{match:bool, similarity:float, extracted:string, expected:string}>, stamp_ok:bool, signature_ok:bool}
     */
    public function validate(array $extracted, array $expected, array $flags = []): array
    {
        $fields = [];
        $allMatch = true;

        foreach ($expected as $field => $spec) {
            $type = $spec['type'] ?? 'text';
            $got = (string) ($extracted[$field] ?? '');
            $want = (string) ($spec['value'] ?? '');

            [$match, $similarity] = $this->compare($type, $got, $want);
            $fields[$field] = [
                'match' => $match,
                'similarity' => round($similarity, 4),
                'extracted' => $got,
                'expected' => $want,
            ];
            $allMatch = $allMatch && $match;
        }

        $stampOk = ! (bool) config('waiver.require_stamp', true) || (bool) ($flags['stamp'] ?? false);
        $signatureOk = ! (bool) config('waiver.require_signature', true) || (bool) ($flags['signature'] ?? false);

        return [
            'valid' => $allMatch && $stampOk && $signatureOk,
            'fields' => $fields,
            'stamp_ok' => $stampOk,
            'signature_ok' => $signatureOk,
        ];
    }

    /**
     * @return array{0: bool, 1: float}
     */
    private function compare(string $type, string $got, string $want): array
    {
        if ($type === 'date') {
            $a = $this->normalizeDate($got);
            $b = $this->normalizeDate($want);
            $match = $a !== null && $a === $b;

            return [$match, $match ? 1.0 : 0.0];
        }

        if ($type === 'time') {
            $a = $this->normalizeTime($got);
            $b = $this->normalizeTime($want);
            $match = $a !== null && $a === $b;

            return [$match, $match ? 1.0 : 0.0];
        }

        $similarity = $this->similarity($this->normalizeText($got), $this->normalizeText($want));
        $threshold = $type === 'name'
            ? (float) config('waiver.name_similarity_threshold', 0.80)
            : 0.90;

        return [$similarity >= $threshold, $similarity];
    }
}
