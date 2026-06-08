<?php

namespace App\Services;

use App\Helpers\FormTemplateHelper;
use App\Models\Form\FormDescription;
use Illuminate\Support\Collection;

/**
 * Maps raw OCR blocks (text + bbox) onto a form's fields.
 *
 * Primary strategy: a field with a calibrated `ocr_region` claims every block
 * whose bbox centre falls inside that region (region is stored as percentages
 * of the image dimensions). Fields without a region fall back to locating the
 * field label in the OCR text and reading the block immediately to its right.
 */
class OcrFieldMatcherService
{
    /**
     * Field types that hold an uploaded image or a repeating table row rather
     * than a single scannable text value — OCR can't fill these, so they are
     * skipped during matching.
     */
    private const NON_TEXT_TYPES = ['file', 'row'];

    /**
     * @param  array<int, array{text: string, bbox: array<int, array<int, float>>, confidence?: float}>  $ocrBlocks
     * @param  Collection<int, FormDescription>  $fields
     * @param  array{width: int, height: int}  $imageDimensions
     * @return array<string, string>  field_key → extracted text
     */
    public function match(array $ocrBlocks, Collection $fields, array $imageDimensions): array
    {
        $width = max(1, (int) ($imageDimensions['width'] ?? 0));
        $height = max(1, (int) ($imageDimensions['height'] ?? 0));

        // Pre-compute each block's centre and a normalised text form.
        $blocks = collect($ocrBlocks)
            ->map(function (array $block) {
                [$cx, $cy] = $this->bboxCenter($block['bbox'] ?? []);

                return [
                    'text' => trim((string) ($block['text'] ?? '')),
                    'cx' => $cx,
                    'cy' => $cy,
                    'bbox' => $block['bbox'] ?? [],
                ];
            })
            ->filter(fn ($b) => $b['text'] !== '')
            ->values();

        $result = [];

        foreach ($fields as $field) {
            // Image uploads (signatures, photos) and table rows aren't scannable text.
            if (in_array((string) $field->field_type, self::NON_TEXT_TYPES, true)) {
                continue;
            }

            $key = FormTemplateHelper::normalizeFieldKey((string) $field->field_key);
            $region = $field->ocr_region;

            if (is_array($region) && $this->regionIsUsable($region)) {
                $value = $this->matchByRegion($blocks, $region, $width, $height);
            } else {
                $value = $this->matchByLabelProximity($blocks, (string) $field->field_label);
            }

            if ($value !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @return array{width: int, height: int}
     */
    public function getImageDimensions(string $imagePath): array
    {
        $size = @getimagesize($imagePath);

        return [
            'width' => (int) ($size[0] ?? 0),
            'height' => (int) ($size[1] ?? 0),
        ];
    }

    /**
     * @param  Collection<int, array{text: string, cx: float, cy: float, bbox: array}>  $blocks
     * @param  array{x_pct: float, y_pct: float, width_pct: float, height_pct: float}  $region
     */
    private function matchByRegion(Collection $blocks, array $region, int $width, int $height): string
    {
        $x1 = (float) $region['x_pct'] * $width;
        $y1 = (float) $region['y_pct'] * $height;
        $x2 = $x1 + (float) $region['width_pct'] * $width;
        $y2 = $y1 + (float) $region['height_pct'] * $height;

        $matched = $blocks
            ->filter(fn ($b) => $b['cx'] >= $x1 && $b['cx'] <= $x2 && $b['cy'] >= $y1 && $b['cy'] <= $y2)
            // Reading order: top-to-bottom, then left-to-right.
            ->sortBy(fn ($b) => [round($b['cy'] / 10), $b['cx']])
            ->pluck('text');

        return trim($matched->implode(' '));
    }

    /**
     * @param  Collection<int, array{text: string, cx: float, cy: float, bbox: array}>  $blocks
     */
    private function matchByLabelProximity(Collection $blocks, string $label): string
    {
        $needle = $this->canonical($label);

        if ($needle === '') {
            return '';
        }

        $labelBlock = $blocks->first(function ($b) use ($needle) {
            $haystack = $this->canonical($b['text']);

            return $haystack !== '' && str_contains($haystack, $needle);
        });

        if ($labelBlock === null) {
            return '';
        }

        // Prefer the nearest block on the same line to the right of the label.
        $sameLineTolerance = 25.0;

        $candidate = $blocks
            ->filter(fn ($b) => $b !== $labelBlock
                && abs($b['cy'] - $labelBlock['cy']) <= $sameLineTolerance
                && $b['cx'] > $labelBlock['cx'])
            ->sortBy('cx')
            ->first();

        return $candidate ? trim((string) $candidate['text']) : '';
    }

    /**
     * @param  array<int, array<int, float>>  $bbox
     * @return array{0: float, 1: float}
     */
    private function bboxCenter(array $bbox): array
    {
        if (empty($bbox)) {
            return [0.0, 0.0];
        }

        $xs = array_map(fn ($p) => (float) ($p[0] ?? 0), $bbox);
        $ys = array_map(fn ($p) => (float) ($p[1] ?? 0), $bbox);

        return [
            (array_sum($xs) / count($xs)),
            (array_sum($ys) / count($ys)),
        ];
    }

    /**
     * @param  array<string, mixed>  $region
     */
    private function regionIsUsable(array $region): bool
    {
        foreach (['x_pct', 'y_pct', 'width_pct', 'height_pct'] as $k) {
            if (! isset($region[$k]) || ! is_numeric($region[$k])) {
                return false;
            }
        }

        return (float) $region['width_pct'] > 0 && (float) $region['height_pct'] > 0;
    }

    private function canonical(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/i', ' ', strtolower($value)) ?? '');
    }
}
