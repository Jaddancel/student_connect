<?php

namespace App\Services\ManualForm;

use App\Forms\FieldType;
use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * Derives a printed field's writable area deterministically from the OOXML
 * table cell that encapsulates it, instead of relying on a vision model's
 * guess. For a field whose `{{key}}` placeholder lives inside a `<w:tbl>`, the
 * cell's position and size (column widths from `w:tblGrid`, row heights from
 * `w:trHeight` or estimated from content) give an exact bounding box and a
 * present/missing/uncertain verdict.
 *
 * Because the geometry is read from a concrete `.docx`, running it against the
 * session's *populated* partial document — not the blank template — captures
 * the distortion a long digital-form entry introduces (a wrapped value grows
 * its row and pushes later fields down or off the page), which is exactly when
 * a hand-written field loses its room to write.
 *
 * Scope: non-repeating, single-page tables only. Tables that clone rows
 * (repeat-marker `#` placeholders) or that spill across pages are left to the
 * vision-model path; {@see mapFieldsToCells()} returns no mapping for them.
 *
 * See docs/manual-form-parsing-contract.md sections 2 and 3.
 */
class DocxTableCellLocator
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Fallback US Letter geometry (twips) when a section omits page size. */
    private const DEFAULT_PAGE_W = 12240;

    private const DEFAULT_PAGE_H = 15840;

    private const DEFAULT_MARGIN = 1440;

    /**
     * Locate the table cell that encapsulates each field's placeholder.
     *
     * @param  array<int,string>  $fieldKeys
     * @return array<string,array{block:int,row:int,col:int,colspan:int,rowspan:int}>
     *                                                                                Keyed by field key; only fields found inside a non-repeating table appear.
     */
    public function mapFieldsToCells(string $docxPath, array $fieldKeys): array
    {
        $model = $this->analyze($docxPath);
        if ($model === null) {
            return [];
        }

        $keys = array_values(array_filter(array_map('strval', $fieldKeys), fn ($k) => $k !== ''));
        $mapping = [];

        foreach ($model['blocks'] as $blockIndex => $block) {
            if (($block['type'] ?? null) !== 'tbl' || ($block['repeating'] ?? false)) {
                continue;
            }

            foreach ($block['rows'] as $rowIndex => $row) {
                foreach ($row['cells'] as $colIndex => $cell) {
                    foreach ($keys as $key) {
                        if (isset($mapping[$key])) {
                            continue;
                        }
                        if ($this->cellHoldsField($cell, $key)) {
                            $mapping[$key] = [
                                'block' => $blockIndex,
                                'row' => $rowIndex,
                                'col' => $colIndex,
                                'colspan' => (int) ($cell['gridSpan'] ?? 1),
                                'rowspan' => (int) ($cell['rowspan'] ?? 1),
                            ];
                        }
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Compute the page, bounding box, and writable-area verdict for a mapped
     * cell against a concrete `.docx` (the raw template at baseline time, or the
     * populated partial document at session time).
     *
     * @param  array{block:int,row:int,col:int,colspan?:int,rowspan?:int}  $cellPath
     * @return array{page:int,bounds:array<int,float>,writable_area:string}|null
     */
    public function geometryFor(string $docxPath, array $cellPath, string $fieldType): ?array
    {
        $model = $this->analyze($docxPath);
        if ($model === null) {
            return null;
        }

        $block = $model['blocks'][$cellPath['block']] ?? null;
        if ($block === null || ($block['type'] ?? null) !== 'tbl') {
            return null;
        }

        $row = $block['rows'][$cellPath['row']] ?? null;
        $cell = $row['cells'][$cellPath['col']] ?? null;
        if ($row === null || $cell === null) {
            return null;
        }

        $page = $model['page'];
        $pageW = $page['width'] > 0 ? $page['width'] : self::DEFAULT_PAGE_W;
        $pageH = $page['height'] > 0 ? $page['height'] : self::DEFAULT_PAGE_H;

        // Horizontal: cumulative column widths for the cell's grid span.
        $xStartTwips = $page['marginLeft'] + $block['tableLeft'] + $this->columnOffset($block, $cell);
        $cellWidth = $this->cellWidth($block, $cell);

        // Vertical: sum block and row heights in document order up to this row.
        $yStartTwips = $page['marginTop'] + $this->heightBefore($model, $cellPath['block'], $cellPath['row']);
        $rowHeight = $row['height'];

        $x1 = $this->clamp01($xStartTwips / $pageW);
        $x2 = $this->clamp01(($xStartTwips + $cellWidth) / $pageW);
        $y1 = $this->clamp01($yStartTwips / $pageH);
        $y2 = $this->clamp01(($yStartTwips + $rowHeight) / $pageH);

        $writable = $this->classify($cell, $cellWidth, $row, $fieldType);

        return [
            'page' => 0,
            'bounds' => [$x1, $y1, $x2, $y2],
            'writable_area' => $writable,
        ];
    }

    /**
     * The normalized horizontal span `[x1, x2]` of a cell's column (accurate,
     * from the grid), used to bound a signature crop that is anchored
     * vertically by the rendered PDF. Null when the cell can't be resolved.
     *
     * @param  array{block:int,row:int,col:int,colspan?:int,rowspan?:int}  $cellPath
     * @return array{0:float,1:float}|null
     */
    public function columnRange(string $docxPath, array $cellPath): ?array
    {
        $model = $this->analyze($docxPath);
        if ($model === null) {
            return null;
        }

        $block = $model['blocks'][$cellPath['block']] ?? null;
        if ($block === null || ($block['type'] ?? null) !== 'tbl') {
            return null;
        }
        $row = $block['rows'][$cellPath['row']] ?? null;
        $cell = $row['cells'][$cellPath['col']] ?? null;
        if ($cell === null) {
            return null;
        }

        $page = $model['page'];
        $pageW = $page['width'] > 0 ? $page['width'] : self::DEFAULT_PAGE_W;

        $xStartTwips = $page['marginLeft'] + $block['tableLeft'] + $this->columnOffset($block, $cell);
        $cellWidth = $this->cellWidth($block, $cell);

        return [
            $this->clamp01($xStartTwips / $pageW),
            $this->clamp01(($xStartTwips + $cellWidth) / $pageW),
        ];
    }

    /**
     * Convenience for baseline generation: map every field to its cell and
     * compute geometry in one pass over the same document.
     *
     * @param  array<int,array{key:string,type:string}>  $fields
     * @return array<string,array{cell_path:array{block:int,row:int,col:int,colspan:int,rowspan:int},page:int,bounds:array<int,float>,writable_area:string}>
     */
    public function locate(string $docxPath, array $fields): array
    {
        $typeByKey = [];
        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $typeByKey[$key] = (string) ($field['type'] ?? FieldType::TEXT);
            }
        }

        $mapping = $this->mapFieldsToCells($docxPath, array_keys($typeByKey));
        $result = [];

        foreach ($mapping as $key => $cellPath) {
            $geometry = $this->geometryFor($docxPath, $cellPath, $typeByKey[$key] ?? FieldType::TEXT);
            if ($geometry !== null) {
                $result[$key] = ['cell_path' => $cellPath] + $geometry;
            }
        }

        return $result;
    }

    /**
     * Parse `word/document.xml` into an ordered, geometry-bearing block model.
     *
     * @return array{page:array{width:int,height:int,marginLeft:int,marginRight:int,marginTop:int,marginBottom:int},blocks:array<int,array<string,mixed>>}|null
     */
    private function analyze(string $docxPath): ?array
    {
        $xml = $this->readDocumentXml($docxPath);
        if ($xml === null) {
            return null;
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);

        $body = $xpath->query('//w:body')->item(0);
        if (! $body instanceof DOMElement) {
            return null;
        }

        $page = $this->pageGeometry($xpath, $body);
        $contentWidth = max(1, $page['width'] - $page['marginLeft'] - $page['marginRight']);

        $blocks = [];
        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $local = $this->localName($node);
            if ($local === 'p') {
                $blocks[] = [
                    'type' => 'p',
                    'height' => $this->paragraphHeight($this->elementText($node, $xpath), $contentWidth),
                ];
            } elseif ($local === 'tbl') {
                $blocks[] = $this->parseTable($node, $xpath, $contentWidth);
            }
        }

        return ['page' => $page, 'blocks' => $blocks];
    }

    /**
     * @return array{type:string,repeating:bool,tableLeft:int,grid:array<int,int>,rows:array<int,array<string,mixed>>}
     */
    private function parseTable(DOMElement $table, DOMXPath $xpath, int $contentWidth): array
    {
        $grid = [];
        foreach ($xpath->query('./w:tblGrid/w:gridCol', $table) as $col) {
            $grid[] = (int) round($this->attr($col, 'w'));
        }

        $tableLeft = 0;
        $indent = $xpath->query('./w:tblPr/w:tblInd', $table)->item(0);
        if ($indent instanceof DOMElement) {
            $tableLeft = (int) round($this->attr($indent, 'w'));
        }

        $repeating = false;
        $rows = [];
        foreach ($xpath->query('./w:tr', $table) as $tr) {
            if (! $tr instanceof DOMElement) {
                continue;
            }
            $row = $this->parseRow($tr, $xpath, $grid, $contentWidth);
            if ($row['repeating']) {
                $repeating = true;
            }
            $rows[] = $row;
        }

        return [
            'type' => 'tbl',
            'repeating' => $repeating,
            'tableLeft' => $tableLeft,
            'grid' => $grid,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int,int>  $grid
     * @return array{repeating:bool,height:int,cells:array<int,array<string,mixed>>}
     */
    private function parseRow(DOMElement $tr, DOMXPath $xpath, array $grid, int $contentWidth): array
    {
        $heightRule = null;
        $heightVal = 0;
        $trHeight = $xpath->query('./w:trPr/w:trHeight', $tr)->item(0);
        if ($trHeight instanceof DOMElement) {
            $heightVal = (int) round($this->attr($trHeight, 'val'));
            $heightRule = $this->attr($trHeight, 'hRule') ?: 'atLeast';
        }

        $cells = [];
        $gridCursor = 0;
        $repeating = false;

        foreach ($xpath->query('./w:tc', $tr) as $tc) {
            if (! $tc instanceof DOMElement) {
                continue;
            }
            $gridSpan = 1;
            $span = $xpath->query('./w:tcPr/w:gridSpan', $tc)->item(0);
            if ($span instanceof DOMElement) {
                $gridSpan = max(1, (int) round($this->attr($span, 'val')));
            }

            $width = 0;
            for ($i = $gridCursor; $i < $gridCursor + $gridSpan; $i++) {
                $width += $grid[$i] ?? 0;
            }
            // Fall back to declared cell width when the grid is absent/short.
            if ($width === 0) {
                $tcW = $xpath->query('./w:tcPr/w:tcW', $tc)->item(0);
                if ($tcW instanceof DOMElement) {
                    $width = (int) round($this->attr($tcW, 'w'));
                }
            }

            $paragraphs = [];
            foreach ($xpath->query('./w:p', $tc) as $p) {
                if ($p instanceof DOMElement) {
                    $paragraphs[] = $this->elementText($p, $xpath);
                }
            }
            $text = implode("\n", $paragraphs);
            if ($this->textHasRepeatMarker($text)) {
                $repeating = true;
            }

            $cells[] = [
                'gridSpan' => $gridSpan,
                'gridStart' => $gridCursor,
                'width' => $width,
                'text' => $text,
                'paragraphs' => $paragraphs,
            ];
            $gridCursor += $gridSpan;
        }

        // Row height: honour an exact rule, otherwise estimate from the tallest
        // cell's wrapped content so a long value grows the row it sits in.
        $estimated = 0;
        foreach ($cells as $cell) {
            $usable = max(1, $cell['width'] - $this->cellMargin());
            $cellHeight = 0;
            foreach ($cell['paragraphs'] as $paragraph) {
                $cellHeight += $this->paragraphHeight($paragraph, $usable);
            }
            $estimated = max($estimated, $cellHeight);
        }
        $estimated = max($estimated, $this->lineHeight());

        $height = match ($heightRule) {
            'exact' => $heightVal,
            'atLeast' => max($heightVal, $estimated),
            default => $estimated,
        };

        return [
            'repeating' => $repeating,
            'height' => max(1, $height),
            'heightRule' => $heightRule,
            'heightVal' => $heightVal,
            'cells' => $cells,
        ];
    }

    /**
     * @param  array{width:int,height:int,marginLeft:int,marginRight:int,marginTop:int,marginBottom:int}  ...
     * @return array{width:int,height:int,marginLeft:int,marginRight:int,marginTop:int,marginBottom:int}
     */
    private function pageGeometry(DOMXPath $xpath, DOMElement $body): array
    {
        $sectPr = $xpath->query('.//w:sectPr', $body)->item(0);

        $pgSz = $sectPr instanceof DOMElement ? $xpath->query('./w:pgSz', $sectPr)->item(0) : null;
        $pgMar = $sectPr instanceof DOMElement ? $xpath->query('./w:pgMar', $sectPr)->item(0) : null;

        $width = $pgSz instanceof DOMElement ? (int) round($this->attr($pgSz, 'w')) : 0;
        $height = $pgSz instanceof DOMElement ? (int) round($this->attr($pgSz, 'h')) : 0;

        return [
            'width' => $width > 0 ? $width : self::DEFAULT_PAGE_W,
            'height' => $height > 0 ? $height : self::DEFAULT_PAGE_H,
            'marginLeft' => $this->marginAttr($pgMar, 'left'),
            'marginRight' => $this->marginAttr($pgMar, 'right'),
            'marginTop' => $this->marginAttr($pgMar, 'top'),
            'marginBottom' => $this->marginAttr($pgMar, 'bottom'),
        ];
    }

    private function marginAttr(?DOMElement $pgMar, string $name): int
    {
        if (! $pgMar instanceof DOMElement) {
            return self::DEFAULT_MARGIN;
        }
        $value = (int) round($this->attr($pgMar, $name));

        return $value > 0 ? $value : self::DEFAULT_MARGIN;
    }

    /**
     * Total height of every block before `$blockIndex`, plus the rows before
     * `$rowIndex` inside the target table block.
     *
     * @param  array{blocks:array<int,array<string,mixed>>}  $model
     */
    private function heightBefore(array $model, int $blockIndex, int $rowIndex): int
    {
        $total = 0;
        foreach ($model['blocks'] as $index => $block) {
            if ($index < $blockIndex) {
                $total += $this->blockHeight($block);

                continue;
            }
            if ($index === $blockIndex && ($block['type'] ?? null) === 'tbl') {
                foreach ($block['rows'] as $r => $row) {
                    if ($r >= $rowIndex) {
                        break;
                    }
                    $total += (int) $row['height'];
                }
            }
            break;
        }

        return $total;
    }

    /**
     * @param  array<string,mixed>  $block
     */
    private function blockHeight(array $block): int
    {
        if (($block['type'] ?? null) === 'p') {
            return (int) $block['height'];
        }
        $height = 0;
        foreach ($block['rows'] ?? [] as $row) {
            $height += (int) $row['height'];
        }

        return $height;
    }

    /**
     * @param  array<string,mixed>  $block
     * @param  array<string,mixed>  $cell
     */
    private function columnOffset(array $block, array $cell): int
    {
        $grid = $block['grid'] ?? [];
        $offset = 0;
        for ($i = 0; $i < (int) ($cell['gridStart'] ?? 0); $i++) {
            $offset += (int) ($grid[$i] ?? 0);
        }

        return $offset;
    }

    /**
     * @param  array<string,mixed>  $block
     * @param  array<string,mixed>  $cell
     */
    private function cellWidth(array $block, array $cell): int
    {
        $width = (int) ($cell['width'] ?? 0);
        if ($width > 0) {
            return $width;
        }

        // Even split of the content width when neither grid nor tcW is present.
        $columns = max(1, count($block['grid'] ?? []));

        return (int) round(array_sum($block['grid'] ?? []) / $columns) ?: 1;
    }

    /**
     * Classify the blank room a field's cell offers. Handwriting is constrained
     * by horizontal room, so the verdict is driven by the cell width minus any
     * label printed before the placeholder on its line. Height only blocks when
     * the row is pinned to an exact, sub-line height (an auto row grows to at
     * least one writable line on print); page position is never used, since the
     * estimate is too coarse to justify blocking a field that visibly fits.
     *
     * @param  array<string,mixed>  $cell
     * @param  array<string,mixed>  $row
     */
    private function classify(array $cell, int $cellWidth, array $row, string $fieldType): string
    {
        // No reliable width measurement — never block on a guess.
        if ($cellWidth <= 0) {
            return 'uncertain';
        }

        $minimums = $this->minimums($fieldType);
        $leadingTwips = $this->leadingLabelWidth($cell);
        $availableWidth = max(0, $cellWidth - $this->cellMargin() - $leadingTwips);

        $widthRatio = $minimums['min_width'] > 0 ? $availableWidth / $minimums['min_width'] : 1.0;

        // Word honours an exact row height by clipping overflow, so a pinned row
        // shorter than a writable line genuinely has nowhere to write.
        $heightRatio = 1.0;
        if (($row['heightRule'] ?? null) === 'exact' && $minimums['min_height'] > 0) {
            $heightRatio = (int) ($row['heightVal'] ?? 0) / $minimums['min_height'];
        }

        $ratio = min($widthRatio, $heightRatio);

        $uncertain = (float) config('manual_form.writable_area.uncertain_ratio', 0.75);
        if ($ratio >= 1.0) {
            return 'present';
        }
        if ($ratio >= $uncertain) {
            return 'uncertain';
        }

        return 'missing';
    }

    /**
     * @return array{min_width:int,min_height:int}
     */
    private function minimums(string $fieldType): array
    {
        $default = (array) config('manual_form.writable_area.default', ['min_height' => 240, 'min_width' => 720]);
        $override = (array) config('manual_form.writable_area.per_type.'.$fieldType, []);

        return [
            'min_width' => (int) ($override['min_width'] ?? $default['min_width'] ?? 720),
            'min_height' => (int) ($override['min_height'] ?? $default['min_height'] ?? 240),
        ];
    }

    /**
     * Twips of label text that sits before a field placeholder on its own line
     * (e.g. "Adviser 1 Name: {{adviser1_name}}"), which eats into the room the
     * user has left to write.
     *
     * @param  array<string,mixed>  $cell
     */
    private function leadingLabelWidth(array $cell): int
    {
        $maxLeading = 0;
        foreach ((array) ($cell['paragraphs'] ?? []) as $paragraph) {
            if (! preg_match('/\{\{|\$\{/', (string) $paragraph, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $before = substr((string) $paragraph, 0, $m[0][1]);
            $maxLeading = max($maxLeading, $this->textWidth($before));
        }

        return $maxLeading;
    }

    /**
     * @param  array<string,mixed>  $cell
     */
    private function cellHoldsField(array $cell, string $key): bool
    {
        $text = (string) ($cell['text'] ?? '');
        if ($text === '') {
            return false;
        }
        $quoted = preg_quote($key, '/');

        return (bool) preg_match('/\{\{\s*'.$quoted.'#?\s*\}\}|\$\{\s*'.$quoted.'#?\s*\}/', $text);
    }

    private function textHasRepeatMarker(string $text): bool
    {
        return (bool) preg_match('/\{\{\s*[a-zA-Z0-9_.:-]+#\s*\}\}|\$\{\s*[a-zA-Z0-9_.:-]+#\s*\}/', $text);
    }

    private function paragraphHeight(string $text, int $usableWidth): int
    {
        $lineWidth = max(1, $usableWidth);
        $lines = max(1, (int) ceil($this->textWidth($text) / $lineWidth));

        return $lines * $this->lineHeight();
    }

    private function textWidth(string $text): int
    {
        $font = $this->fontTwips();
        $factor = (float) config('manual_form.row_estimation.avg_char_width_factor', 0.5);

        return (int) round(mb_strlen(trim($text)) * $font * $factor);
    }

    private function lineHeight(): int
    {
        $factor = (float) config('manual_form.row_estimation.line_height_factor', 1.15);

        return (int) round($this->fontTwips() * $factor);
    }

    private function fontTwips(): int
    {
        return (int) config('manual_form.row_estimation.default_font_twips', 240);
    }

    private function cellMargin(): int
    {
        return (int) config('manual_form.row_estimation.cell_margin_twips', 216);
    }

    private function readDocumentXml(string $docxPath): ?string
    {
        if (! is_file($docxPath)) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return null;
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return is_string($xml) && $xml !== '' ? $xml : null;
    }

    private function elementText(DOMElement $element, DOMXPath $xpath): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t', $element) as $node) {
            $parts[] = $node->textContent;
        }

        return html_entity_decode(implode('', $parts), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function attr(DOMElement $element, string $name): string
    {
        return $element->getAttributeNS(self::WORD_NS, $name);
    }

    private function localName(DOMElement $element): string
    {
        return $element->localName ?? '';
    }

    private function clamp01(float $value): float
    {
        return round(max(0.0, min(1.0, $value)), 4);
    }
}
