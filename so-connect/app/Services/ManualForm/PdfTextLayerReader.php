<?php

namespace App\Services\ManualForm;

use App\Forms\FieldType;
use App\Services\DocumentVision\DocumentVisionResultNormalizer;
use App\Services\OcrClient;
use Illuminate\Support\Facades\File;

/**
 * Reads values typed directly onto the downloaded partial PDF from the PDF's
 * own text instead of its pixels. Two sources are combined:
 *
 *  - the page's text (`pdftotext -bbox`): edits made in a document editor and
 *    re-exported, or flattened typewriter text;
 *  - annotations and fillable-form values (OCR sidecar `/pdf-annotations`):
 *    text boxes added in a browser PDF viewer, Acrobat Fill & Sign, etc.
 *
 * The scan's text is only trusted when it is clearly the same document as the
 * printed partial: same page count and shape, and most of the partial's
 * printed words still at the same spots. Printed words are subtracted, so only
 * newly typed text remains; each typed word is assigned to the field whose read
 * region contains it. Anything inconclusive returns no values, leaving the
 * field to the vision model.
 */
class PdfTextLayerReader
{
    public function __construct(
        private readonly SentinelPdfLocator $sentinel,
        private readonly OcrClient $ocr,
        private readonly DocumentVisionResultNormalizer $normalizer,
    ) {}

    /**
     * @param  array<int,array{key:string,type:string,options?:mixed,page:int,region:array<int,float>,bounds?:?array<int,float>}>  $fields
     * @return array<string,mixed> field key => typed value
     */
    public function read(string $scanPdfPath, ?string $referencePdfPath, array $fields): array
    {
        if (! (bool) config('manual_form.text_layer.enabled', true) || $fields === []
            || $referencePdfPath === null || ! is_file($referencePdfPath) || ! is_file($scanPdfPath)) {
            return [];
        }

        $reference = $this->sentinel->wordsFromPdf($referencePdfPath);
        $scan = $this->sentinel->wordsFromPdf($scanPdfPath);
        if ($reference === [] || $scan === [] || ! $this->sameGeometry($reference, $scan)) {
            return [];
        }

        $typed = $this->typedWords($reference, $scan);
        if ($typed === null) {
            return [];
        }
        foreach ($this->annotationWords($scanPdfPath, count($scan), $typed) as $page => $words) {
            $typed[$page] = array_merge($typed[$page] ?? [], $words);
        }

        $byField = $this->assign($typed, $fields);
        if ($byField === []) {
            return [];
        }

        $raw = [];
        $requested = [];
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            if (! isset($byField[$key])) {
                continue;
            }
            $text = $this->joinWords($byField[$key], (string) ($field['type'] ?? ''));
            if ($text === '') {
                continue;
            }
            $raw[$key] = ['value' => $text, 'confidence' => 1.0];
            $requested[] = ['key' => $key, 'type' => (string) ($field['type'] ?? ''), 'options' => $field['options'] ?? null];
        }

        if ($requested === []) {
            return [];
        }

        return $this->normalizer->normalize($requested, ['fields' => $raw])['values'];
    }

    /**
     * @param  array<int,array{width:float,height:float}>  $reference
     * @param  array<int,array{width:float,height:float}>  $scan
     */
    private function sameGeometry(array $reference, array $scan): bool
    {
        if (count($reference) !== count($scan)) {
            return false;
        }
        foreach ($reference as $index => $page) {
            $other = $scan[$index] ?? null;
            if ($other === null || $page['height'] <= 0 || $other['height'] <= 0) {
                return false;
            }
            $ratio = ($page['width'] / $page['height']) / ($other['width'] / $other['height']);
            if (abs($ratio - 1.0) > 0.02) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scan words not printed on the reference, per page — or null when too few
     * printed words reappear in place (a re-laid-out document, or a scanner's
     * OCR text layer), so the scan's text cannot be trusted.
     *
     * @param  array<int,array{words:array<int,array<string,mixed>>}>  $reference
     * @param  array<int,array{words:array<int,array<string,mixed>>}>  $scan
     * @return array<int,array<int,array{x1:float,y1:float,x2:float,y2:float,text:string}>>|null
     */
    private function typedWords(array $reference, array $scan): ?array
    {
        $tolerance = (float) config('manual_form.text_layer.position_tolerance', 0.01);
        $referenceTotal = 0;
        $matched = 0;
        $typed = [];

        foreach ($scan as $index => $page) {
            $remaining = $reference[$index]['words'] ?? [];
            $referenceTotal += count($remaining);

            foreach ($page['words'] as $word) {
                $hit = null;
                foreach ($remaining as $i => $printed) {
                    if ($printed['text'] === $word['text']
                        && abs($this->cx($printed) - $this->cx($word)) <= $tolerance
                        && abs($this->cy($printed) - $this->cy($word)) <= $tolerance) {
                        $hit = $i;
                        break;
                    }
                }
                if ($hit !== null) {
                    unset($remaining[$hit]);
                    $matched++;

                    continue;
                }
                $typed[$index][] = $word;
            }
        }

        $minShare = (float) config('manual_form.text_layer.min_reference_match', 0.8);
        if ($referenceTotal === 0 || $matched / $referenceTotal < $minShare) {
            return null;
        }

        return $typed;
    }

    /**
     * Annotation / fillable-field text as positioned words. A text box can be
     * much larger than its text, which starts at its top-left, so the box is
     * reduced to its first line. Some editors (e.g. OnlyOffice) also draw the
     * annotation into the page text; an annotation whose text already sits
     * inside its box in `$pageTyped` is skipped so it is not read twice.
     *
     * @param  array<int,array<int,array{x1:float,y1:float,x2:float,y2:float,text:string}>>  $pageTyped
     * @return array<int,array<int,array{x1:float,y1:float,x2:float,y2:float,text:string}>>
     */
    private function annotationWords(string $scanPdfPath, int $pageCount, array $pageTyped = []): array
    {
        $result = $this->ocr->pdfAnnotations((string) File::get($scanPdfPath));
        if (! ($result['ok'] ?? false)) {
            return [];
        }

        $pages = (array) ($result['pages'] ?? []);
        if (count($pages) !== $pageCount) {
            return [];
        }

        $words = [];
        foreach (array_values($pages) as $index => $page) {
            foreach ((array) ($page['items'] ?? []) as $item) {
                $text = trim((string) ($item['text'] ?? ''));
                $rect = array_values((array) ($item['rect'] ?? []));
                if ($text === '' || count($rect) !== 4) {
                    continue;
                }
                [$x1, $y1, $x2, $y2] = array_map('floatval', $rect);
                if ($x2 <= $x1 || $y2 <= $y1) {
                    continue;
                }
                if ($this->alreadyInPageText($text, [$x1, $y1, $x2, $y2], $pageTyped[$index] ?? [])) {
                    continue;
                }
                $words[$index][] = [
                    'x1' => $x1,
                    'y1' => $y1,
                    'x2' => min($x2, $x1 + 0.02),
                    'y2' => min($y2, $y1 + 0.016),
                    'text' => $text,
                ];
            }
        }

        return $words;
    }

    /**
     * Whether the page's own typed words inside `$box` (slightly padded) spell
     * out `$text`, ignoring whitespace and case.
     *
     * @param  array<int,float>  $box
     * @param  array<int,array{x1:float,y1:float,x2:float,y2:float,text:string}>  $pageWords
     */
    private function alreadyInPageText(string $text, array $box, array $pageWords): bool
    {
        $pad = 0.01;
        $inside = array_filter($pageWords, function (array $w) use ($box, $pad) {
            $cx = $this->cx($w);
            $cy = $this->cy($w);

            return $cx >= $box[0] - $pad && $cx <= $box[2] + $pad && $cy >= $box[1] - $pad && $cy <= $box[3] + $pad;
        });
        if ($inside === []) {
            return false;
        }

        $squash = fn (string $v) => mb_strtolower((string) preg_replace('/\s+/u', '', $v));
        $pageText = $squash($this->joinWords(array_values($inside), FieldType::TEXT));

        return $pageText !== '' && str_contains($pageText, $squash($text));
    }

    /**
     * Assign each typed word to the field whose read region contains its
     * centre; where regions overlap, the field whose own bounds are closest.
     *
     * @param  array<int,array<int,array{x1:float,y1:float,x2:float,y2:float,text:string}>>  $typed
     * @param  array<int,array<string,mixed>>  $fields
     * @return array<string,array<int,array{x1:float,y1:float,x2:float,y2:float,text:string}>>
     */
    private function assign(array $typed, array $fields): array
    {
        $byField = [];
        foreach ($typed as $page => $words) {
            foreach ($words as $word) {
                $cx = $this->cx($word);
                $cy = $this->cy($word);
                $best = null;
                $bestDistance = INF;
                foreach ($fields as $field) {
                    if ((int) ($field['page'] ?? 0) !== (int) $page) {
                        continue;
                    }
                    [$x1, $y1, $x2, $y2] = $field['region'];
                    if ($cx < $x1 || $cx > $x2 || $cy < $y1 || $cy > $y2) {
                        continue;
                    }
                    $distance = $this->distanceTo($cx, $cy, $field['bounds'] ?? $field['region']);
                    if ($distance < $bestDistance) {
                        $best = (string) $field['key'];
                        $bestDistance = $distance;
                    }
                }
                if ($best !== null) {
                    $byField[$best][] = $word;
                }
            }
        }

        return $byField;
    }

    /**
     * Words in reading order: grouped into lines by vertical centre, each line
     * left to right. Multi-line text keeps its line breaks only for textareas.
     *
     * @param  array<int,array{x1:float,y1:float,x2:float,y2:float,text:string}>  $words
     */
    private function joinWords(array $words, string $type): string
    {
        usort($words, fn ($a, $b) => $this->cy($a) <=> $this->cy($b));

        $lines = [];
        foreach ($words as $word) {
            $last = array_key_last($lines);
            $height = max(0.004, $word['y2'] - $word['y1']);
            if ($last !== null && abs($lines[$last]['cy'] - $this->cy($word)) <= $height / 2) {
                $lines[$last]['words'][] = $word;

                continue;
            }
            $lines[] = ['cy' => $this->cy($word), 'words' => [$word]];
        }

        $separator = $type === FieldType::TEXTAREA ? "\n" : ' ';
        $text = implode($separator, array_map(function (array $line) {
            usort($line['words'], fn ($a, $b) => $a['x1'] <=> $b['x1']);

            return implode(' ', array_map(fn ($w) => $w['text'], $line['words']));
        }, $lines));

        if ($type !== FieldType::TEXTAREA) {
            $text = (string) preg_replace('/\s+/', ' ', $text);
        }

        return trim($text);
    }

    /**
     * @param  array<int,float>  $box
     */
    private function distanceTo(float $x, float $y, array $box): float
    {
        [$x1, $y1, $x2, $y2] = array_map('floatval', array_values($box));
        $dx = max($x1 - $x, 0.0, $x - $x2);
        $dy = max($y1 - $y, 0.0, $y - $y2);

        return sqrt($dx * $dx + $dy * $dy);
    }

    /** @param  array{x1:float,x2:float}  $word */
    private function cx(array $word): float
    {
        return ($word['x1'] + $word['x2']) / 2;
    }

    /** @param  array{y1:float,y2:float}  $word */
    private function cy(array $word): float
    {
        return ($word['y1'] + $word['y2']) / 2;
    }
}
