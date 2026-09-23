<?php

namespace App\Services\DocumentVision;

/**
 * Strictly validates and normalizes raw document-vision model JSON against the
 * fields that were actually requested. Unknown keys are dropped, malformed
 * bounding boxes discarded, option labels mapped to canonical values, and
 * confidence clamped. Signatures are reduced to presence/bounds and never
 * appear as text values.
 *
 * See docs/manual-form-parsing-contract.md sections 4.2–4.3.
 */
class DocumentVisionResultNormalizer
{
    /**
     * @param  array<int,array{key:string,type:string,options?:?array<int,array{value:string,label:string}>,page?:int}>  $requestedFields
     * @param  array<string,mixed>  $raw  decoded model JSON
     * @param  array<string,mixed>  $knownValues  never overwritten
     * @return array{
     *     values: array<string,mixed>,
     *     signatures: array<string,array{present:bool,page:int,bounds:?array<int,float>}>,
     *     confidence: array<string,float>,
     *     unresolved: array<int,string>,
     *     warnings: array<int,string>
     * }
     */
    public function normalize(array $requestedFields, array $raw, array $knownValues = []): array
    {
        $rawFields = is_array($raw['fields'] ?? null) ? $raw['fields'] : [];

        $values = [];
        $signatures = [];
        $confidence = [];
        $unresolved = [];

        foreach ($requestedFields as $field) {
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? '');
            if ($key === '' || array_key_exists($key, $knownValues)) {
                continue;
            }

            $entry = is_array($rawFields[$key] ?? null) ? $rawFields[$key] : null;
            $conf = $this->clampConfidence($entry['confidence'] ?? null);

            if ($type === 'signature') {
                $present = (bool) ($entry['present'] ?? false);
                $signatures[$key] = [
                    'present' => $present,
                    'page' => (int) ($field['page'] ?? ($entry['page'] ?? 0)),
                    'bounds' => $this->validBounds($entry['bounds'] ?? null),
                ];
                if ($conf !== null) {
                    $confidence[$key] = $conf;
                }
                if (! $present) {
                    $unresolved[] = $key;
                }

                continue;
            }

            $value = $this->resolveValue($type, $field['options'] ?? null, $entry['value'] ?? null);

            if ($value === null) {
                $unresolved[] = $key;

                continue;
            }

            $values[$key] = $value;
            if ($conf !== null) {
                $confidence[$key] = $conf;
            }
        }

        return [
            'values' => $values,
            'signatures' => $signatures,
            'confidence' => $confidence,
            'unresolved' => array_values(array_unique($unresolved)),
            'warnings' => $this->stringList($raw['warnings'] ?? null),
        ];
    }

    /**
     * Map a model-returned value to a canonical, type-valid value, or null.
     *
     * @param  array<int,array{value:string,label:string}>|null  $options
     */
    private function resolveValue(string $type, ?array $options, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($type, ['select', 'radio', 'checkbox'], true) && is_array($options) && $options !== []) {
            return $this->matchOption($options, $value);
        }

        if (is_scalar($value)) {
            return trim((string) $value) === '' ? null : trim((string) $value);
        }

        return null;
    }

    /**
     * Match a model answer to a declared option by exact value, then by
     * case-insensitive label. Returns the canonical value or null.
     *
     * @param  array<int,array{value:string,label:string}>  $options
     */
    private function matchOption(array $options, mixed $answer): ?string
    {
        $needle = mb_strtolower(trim((string) $answer));
        if ($needle === '') {
            return null;
        }

        foreach ($options as $option) {
            if (isset($option['value']) && (string) $option['value'] === (string) $answer) {
                return (string) $option['value'];
            }
        }

        foreach ($options as $option) {
            if (isset($option['label']) && mb_strtolower(trim((string) $option['label'])) === $needle) {
                return (string) $option['value'];
            }
        }

        return null;
    }

    /**
     * @return array<int,float>|null a valid [x1,y1,x2,y2] box in [0,1] or null
     */
    private function validBounds(mixed $bounds): ?array
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
        if ($x1 > $x2 || $y1 > $y2) {
            return null;
        }

        return $box;
    }

    private function clampConfidence(mixed $confidence): ?float
    {
        if (! is_numeric($confidence)) {
            return null;
        }

        return max(0.0, min(1.0, (float) $confidence));
    }

    /**
     * @return array<int,string>
     */
    private function stringList(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($v) => is_string($v) ? $v : null, $list),
            fn ($v) => $v !== null && $v !== '',
        ));
    }
}
