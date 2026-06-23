<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Maps raw PaddleOCR bbox text blocks to form fields.
 *
 * Primary strategy: a block whose bbox centre falls inside a field's
 * configured ocr_region (stored as percentages of image width/height) is
 * assigned to that field. Fields without an ocr_region fall back to
 * label-text proximity: the block nearest-and-to-the-right of a block whose
 * text matches the field label.
 */
class OcrFieldMatcherService
{
    /**
     * @param  array<int, array{text: string, bbox: array, confidence?: float}>  $ocrBlocks
     * @param  Collection<int, \App\Models\Form\FormDescription>  $fields
     * @param  array{width: int, height: int}  $imageDimensions
     * @return array<string, string>  field_key => extracted text
     */
    public function match(array $ocrBlocks, Collection $fields, array $imageDimensions): array
    {
        $width = max(1, (int) ($imageDimensions['width'] ?? 0));
        $height = max(1, (int) ($imageDimensions['height'] ?? 0));

        $result = [];
        $usedBlockIndexes = [];

        // 1. Region-based matching for fields that have an ocr_region.
        foreach ($fields as $field) {
            $region = $field->ocr_region;
            if (! is_array($region) || $region === []) {
                continue;
            }

            $matchedText = [];
            foreach ($ocrBlocks as $index => $block) {
                if (isset($usedBlockIndexes[$index])) {
                    continue;
                }

                [$cx, $cy] = $this->bboxCenter($block['bbox'] ?? []);
                if ($this->centerInRegion($cx / $width, $cy / $height, $region)) {
                    $matchedText[] = trim((string) ($block['text'] ?? ''));
                    $usedBlockIndexes[$index] = true;
                }
            }

            if ($matchedText !== []) {
                $result[$field->field_key] = trim(implode(' ', array_filter($matchedText)));
            }
        }

        // 2. Inline "Label: Value" matching — a single block holds both the
        //    label and its value (common on printed forms, e.g. "Position: President").
        foreach ($fields as $field) {
            if (array_key_exists($field->field_key, $result)) {
                continue;
            }

            $value = $this->matchByInlineLabel($field, $ocrBlocks, $usedBlockIndexes);
            if ($value !== null) {
                $result[$field->field_key] = $value;
            }
        }

        // 3. Label-proximity fallback: value sits in a separate block to the
        //    right of a label block.
        foreach ($fields as $field) {
            if (array_key_exists($field->field_key, $result)) {
                continue;
            }

            $value = $this->matchByLabelProximity($field, $ocrBlocks, $usedBlockIndexes);
            if ($value !== null) {
                $result[$field->field_key] = $value;
            }
        }

        return $result;
    }

    /**
     * Matches a field against a single block of the form "Label: Value", returning
     * the trimmed value when the text before the colon equals the field label.
     *
     * @param  array<int, array<string, mixed>>  $ocrBlocks
     * @param  array<int, bool>  $usedBlockIndexes
     */
    private function matchByInlineLabel($field, array $ocrBlocks, array &$usedBlockIndexes): ?string
    {
        $label = strtolower(trim((string) ($field->field_label ?? $field->field_key)));
        if ($label === '') {
            return null;
        }

        foreach ($ocrBlocks as $index => $block) {
            if (isset($usedBlockIndexes[$index])) {
                continue;
            }

            $text = trim((string) ($block['text'] ?? ''));
            $colon = strpos($text, ':');
            if ($colon === false) {
                continue;
            }

            $before = strtolower(trim(substr($text, 0, $colon)));
            $after = trim(substr($text, $colon + 1));

            if ($after !== '' && $before === $label) {
                $usedBlockIndexes[$index] = true;

                return $after;
            }
        }

        return null;
    }

    /**
     * Reads image pixel dimensions using getimagesize().
     *
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
     * Finds a block whose text resembles the field label, then returns the text
     * of the nearest block to its right on the same horizontal band.
     *
     * @param  array<int, array<string, mixed>>  $ocrBlocks
     * @param  array<int, bool>  $usedBlockIndexes
     */
    private function matchByLabelProximity($field, array $ocrBlocks, array &$usedBlockIndexes): ?string
    {
        $label = strtolower(trim((string) ($field->field_label ?? $field->field_key)));
        if ($label === '') {
            return null;
        }

        $labelBlock = null;
        foreach ($ocrBlocks as $block) {
            $text = strtolower(trim((string) ($block['text'] ?? '')));
            $text = rtrim($text, ': ');
            if ($text !== '' && str_contains($text, $label)) {
                $labelBlock = $block;
                break;
            }
        }

        if ($labelBlock === null) {
            return null;
        }

        [$labelCx, $labelCy] = $this->bboxCenter($labelBlock['bbox'] ?? []);

        $best = null;
        $bestDistance = PHP_FLOAT_MAX;
        foreach ($ocrBlocks as $index => $block) {
            if (isset($usedBlockIndexes[$index])) {
                continue;
            }

            [$cx, $cy] = $this->bboxCenter($block['bbox'] ?? []);

            // Same horizontal band and to the right of the label.
            if ($cx <= $labelCx || abs($cy - $labelCy) > 25) {
                continue;
            }

            $distance = $cx - $labelCx;
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = ['index' => $index, 'text' => trim((string) ($block['text'] ?? ''))];
            }
        }

        if ($best === null) {
            return null;
        }

        $usedBlockIndexes[$best['index']] = true;

        return $best['text'];
    }

    /**
     * @param  array  $bbox  PaddleOCR quad: [[x1,y1],[x2,y2],[x3,y3],[x4,y4]]
     * @return array{0: float, 1: float}
     */
    private function bboxCenter(array $bbox): array
    {
        if ($bbox === []) {
            return [0.0, 0.0];
        }

        $xs = array_map(fn ($p) => (float) ($p[0] ?? 0), $bbox);
        $ys = array_map(fn ($p) => (float) ($p[1] ?? 0), $bbox);

        return [array_sum($xs) / count($xs), array_sum($ys) / count($ys)];
    }

    /**
     * @param  array{x_pct?: float, y_pct?: float, width_pct?: float, height_pct?: float}  $region
     */
    private function centerInRegion(float $xPct, float $yPct, array $region): bool
    {
        $x = (float) ($region['x_pct'] ?? 0);
        $y = (float) ($region['y_pct'] ?? 0);
        $w = (float) ($region['width_pct'] ?? 0);
        $h = (float) ($region['height_pct'] ?? 0);

        return $xPct >= $x && $xPct <= ($x + $w)
            && $yPct >= $y && $yPct <= ($y + $h);
    }
}
