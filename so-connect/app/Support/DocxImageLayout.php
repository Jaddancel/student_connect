<?php

namespace App\Support;

/**
 * Lays picture placeholders out against the table cells they were typed into,
 * so a generated image takes the size of its cell rather than a fixed default.
 *
 *  - A single image (signature, image upload, `{{profile.signature}}` …) is
 *    expected to sit alone in a one-row, one-column table: it is fitted inside
 *    that cell — the cell's width (from the table grid) and the row's height,
 *    both less the cell margins — keeping its aspect ratio.
 *  - A photo set is expected to sit in a one-row table whose columns are the
 *    picture slots. Its images fill the token's cell and the cells after it,
 *    left to right; when the columns run out the row is cloned (like a table
 *    field's repeating row) and filling continues in the copy. Every picture
 *    is fitted to its own cell. Cloned rows keep the template row's cell
 *    margins, widths and height.
 *
 * Tokens outside any table keep the bounded default size. Cell margins are
 * resolved the way Word/OnlyOffice do: the cell's own `tcMar`, then the
 * table's `tblCellMar`, then its table style (and the default table style),
 * then Word's built-in 0.19 cm left/right inset. A row's height bounds the
 * picture when it is exact, or an "at least" height of 1 cm or more; shorter
 * rows grow to fit a width-fitted picture (capped at the page height).
 *
 * Each placement is renamed to a unique placeholder, returned with the image
 * it receives and the box (twips) it must fit, for the caller to hand to
 * PhpWord's `setImageValue()`.
 */
final class DocxImageLayout
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Word's built-in table cell inset: 108 twips (0.19 cm) left and right. */
    private const DEFAULT_MARGINS = ['top' => 0, 'left' => 108, 'bottom' => 0, 'right' => 108];

    /** Fallback cap for an image whose row has no set height: 9in. */
    private const DEFAULT_MAX_HEIGHT = 12960;

    /**
     * Smallest "at least" row height (1 cm) read as an authored picture
     * frame; exact heights always count.
     */
    private const SIZED_ROW_MIN = 567;

    /**
     * Room left under a picture in a fixed-height row: Word lays an inline
     * picture on the text baseline, so the line also carries the font's
     * descent and a picture fitted to the full height would be clipped.
     */
    private const BASELINE_ALLOWANCE = 60;

    private const PPR_AFTER_SPACING = [
        'ind', 'contextualSpacing', 'mirrorIndents', 'suppressOverlap', 'jc', 'textDirection',
        'textAlignment', 'textboxTightWrap', 'outlineLvl', 'divId', 'cnfStyle', 'rPr', 'sectPr', 'pPrChange',
    ];

    /** @var array<string, \DOMElement> styleId => table style element */
    private array $tableStyles = [];

    private ?\DOMElement $defaultTableStyle = null;

    private ?\DOMXPath $stylesXpath = null;

    private int $counter = 0;

    public function __construct(?string $stylesXml = null)
    {
        if ($stylesXml === null || $stylesXml === '') {
            return;
        }

        $styles = new \DOMDocument;
        if (! @$styles->loadXML($stylesXml)) {
            return;
        }

        $this->stylesXpath = new \DOMXPath($styles);
        $this->stylesXpath->registerNamespace('w', self::W_NS);

        foreach ($this->stylesXpath->query('//w:style[@w:type="table"]') ?: [] as $style) {
            /** @var \DOMElement $style */
            $this->tableStyles[$style->getAttributeNS(self::W_NS, 'styleId')] = $style;
            if (in_array($style->getAttributeNS(self::W_NS, 'default'), ['1', 'true', 'on'], true)) {
                $this->defaultTableStyle = $style;
            }
        }
    }

    /**
     * Rename each picture placeholder in a document part to a unique one and
     * lay photo sets out across their row.
     *
     * @param  array<string, array{paths: array<int, string>, set: bool}>  $jobs  placeholder name => images
     * @param  callable(string): string  $macro  wraps a name in the active delimiters
     * @return array{0: string, 1: array<string, array{path: string, box: ?array{width: int, height: ?int}}>}
     */
    public function place(string $xml, array $jobs, callable $macro): array
    {
        $relevant = [];
        foreach ($jobs as $name => $job) {
            if ($job['paths'] !== [] && str_contains($xml, $this->escape($macro((string) $name)))) {
                $relevant[(string) $name] = $job;
            }
        }
        if ($relevant === []) {
            return [$xml, []];
        }

        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = true;
        if (! @$dom->loadXML($xml)) {
            return [$xml, []];
        }
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::W_NS);

        $maxHeight = $this->printableHeight($xpath);
        $placements = [];

        foreach ($relevant as $name => $job) {
            $token = $macro($name);

            // Re-queried per occurrence: a photo set clones rows, which adds
            // nodes (but never new copies of a token it has consumed).
            for ($guard = 0; $guard < 500; $guard++) {
                $text = $this->firstTextContaining($xpath, $token);
                if ($text === null) {
                    break;
                }

                $cell = $this->ancestor($text, 'tc');

                if ($job['set'] && $cell !== null) {
                    $placements += $this->placeSet($xpath, $text, $cell, $token, $name, $job['paths'], $macro, $maxHeight);

                    continue;
                }

                $box = $cell !== null ? $this->cellBox($xpath, $cell, $maxHeight) : null;
                $paths = $job['set'] ? $job['paths'] : [$job['paths'][0]];
                $names = [];
                foreach ($paths as $path) {
                    $unique = $this->uniqueName($name);
                    $names[] = $macro($unique);
                    $placements[$unique] = ['path' => $path, 'box' => $box];
                }

                $paragraph = $this->ancestor($text, 'p');
                $alone = $paragraph !== null && trim($paragraph->textContent) === $token;
                $text->nodeValue = $this->replaceFirst($text->nodeValue ?? '', $token, implode(' ', $names));
                if ($cell !== null && $alone) {
                    $this->tightenParagraph($paragraph);
                }
            }
        }

        return [(string) $dom->saveXML(), $placements];
    }

    /**
     * Width/height (in PhpWord's `pt` units) of an image fitted inside a box,
     * aspect ratio kept. With no box, PhpWord's bounded default is used.
     *
     * @param  array{width: int, height: ?int}|null  $box  twips
     * @return array{path: string, width: int|string, height: int|string, ratio: bool}
     */
    public static function imageValue(string $path, ?array $box): array
    {
        $size = @getimagesize($path);
        if ($box === null || ! is_array($size) || $size[0] <= 0 || $size[1] <= 0 || $box['width'] <= 0) {
            return ['path' => $path, 'width' => 200, 'height' => 120, 'ratio' => true];
        }

        [$imageWidth, $imageHeight] = $size;
        $scale = $box['width'] / $imageWidth;
        if ($box['height'] !== null && $box['height'] > 0) {
            $scale = min($scale, $box['height'] / $imageHeight);
        }

        return [
            'path' => $path,
            'width' => self::points($imageWidth * $scale),
            'height' => self::points($imageHeight * $scale),
            'ratio' => false,
        ];
    }

    /**
     * Spread a photo set across the token's cell and the cells after it in
     * the row, cloning the row while images remain.
     *
     * @param  array<int, string>  $paths
     * @param  callable(string): string  $macro
     * @return array<string, array{path: string, box: ?array{width: int, height: ?int}}>
     */
    private function placeSet(
        \DOMXPath $xpath,
        \DOMElement $text,
        \DOMElement $cell,
        string $token,
        string $name,
        array $paths,
        callable $macro,
        int $maxHeight,
    ): array {
        $row = $this->ancestor($cell, 'tr');
        if ($row === null) {
            return [];
        }

        $cells = $this->rowCells($row);
        $start = array_search($cell, $cells, true);
        $slots = array_slice($cells, (int) $start);
        $perRow = max(1, count($slots));

        // Where in the token's cell the picture goes: the token's paragraph.
        $paragraph = $this->ancestor($text, 'p');
        $tokenParagraph = $paragraph !== null ? $this->paragraphIndex($cell, $paragraph) : 0;

        $text->nodeValue = $this->replaceFirst($text->nodeValue ?? '', $token, '');

        $rows = [$row];
        $needed = (int) ceil(count($paths) / $perRow);
        for ($i = 1; $i < $needed; $i++) {
            $clone = $row->cloneNode(true);
            $last = end($rows);
            $last->parentNode?->insertBefore($clone, $last->nextSibling);
            $rows[] = $clone;
        }

        $boxes = [];
        $placements = [];
        foreach ($paths as $i => $path) {
            $column = $i % $perRow;
            $target = array_slice($this->rowCells($rows[intdiv($i, $perRow)]), (int) $start)[$column] ?? null;
            if ($target === null) {
                continue;
            }

            $boxes[$column] ??= $this->cellBox($xpath, $target, $maxHeight);
            $unique = $this->uniqueName($name);
            $placements[$unique] = ['path' => $path, 'box' => $boxes[$column]];

            $targetParagraph = $this->cellParagraph($target, $column === 0 ? $tokenParagraph : 0);
            $this->appendRun($targetParagraph, $macro($unique));
            $this->tightenParagraph($targetParagraph);
        }

        return $placements;
    }

    /**
     * The box (twips) a picture in this cell may fill: grid width and row
     * height, less the cell margins. Height is null for rows that grow with
     * their content; the width-fitted picture is then capped at the page.
     *
     * @return array{width: int, height: ?int}|null
     */
    private function cellBox(\DOMXPath $xpath, \DOMElement $cell, int $maxHeight): ?array
    {
        $row = $this->ancestor($cell, 'tr');
        $table = $row !== null ? $this->ancestor($row, 'tbl') : null;
        if ($row === null || $table === null) {
            return null;
        }

        $width = $this->cellWidth($xpath, $table, $row, $cell);
        if ($width === null) {
            return null;
        }

        $margins = $this->cellMargins($xpath, $table, $cell);
        $width -= $margins['left'] + $margins['right'];

        $height = null;
        $rowHeight = $this->first($xpath, 'w:trPr/w:trHeight', $row);
        if ($rowHeight !== null) {
            $rule = $this->attr($rowHeight, 'hRule');
            $value = (int) $this->attr($rowHeight, 'val');
            // An "at least" height below SIZED_ROW_MIN is the editor's one-line
            // minimum that OnlyOffice stamps on every row, not a picture frame.
            $sized = $rule === 'exact' || ($rule !== 'auto' && $value >= self::SIZED_ROW_MIN);
            if ($sized && $value > 0) {
                $height = $value - $margins['top'] - $margins['bottom'] - self::BASELINE_ALLOWANCE;
            }
        }

        if ($width <= 0) {
            return null;
        }

        return ['width' => $width, 'height' => $height !== null ? max(1, $height) : $maxHeight];
    }

    /**
     * Cell width in twips from the table grid (its laid-out width), falling
     * back to the cell's preferred `tcW` when the grid doesn't cover it.
     */
    private function cellWidth(\DOMXPath $xpath, \DOMElement $table, \DOMElement $row, \DOMElement $cell): ?int
    {
        $grid = [];
        foreach ($xpath->query('w:tblGrid/w:gridCol', $table) ?: [] as $column) {
            /** @var \DOMElement $column */
            $grid[] = (int) $this->attr($column, 'w');
        }

        $before = $this->first($xpath, 'w:trPr/w:gridBefore', $row);
        $index = $before !== null ? (int) $this->attr($before, 'val') : 0;
        foreach ($this->rowCells($row) as $sibling) {
            if ($sibling === $cell) {
                break;
            }
            $index += $this->span($xpath, $sibling);
        }

        $span = $this->span($xpath, $cell);
        if ($grid !== [] && $index + $span <= count($grid)) {
            $width = array_sum(array_slice($grid, $index, $span));
            if ($width > 0) {
                return $width;
            }
        }

        $preferred = $this->first($xpath, 'w:tcPr/w:tcW', $cell);
        if ($preferred !== null && in_array($this->attr($preferred, 'type'), ['dxa', ''], true)) {
            $width = (int) $this->attr($preferred, 'w');

            return $width > 0 ? $width : null;
        }

        return null;
    }

    /**
     * @return array{top: int, left: int, bottom: int, right: int}
     */
    private function cellMargins(\DOMXPath $xpath, \DOMElement $table, \DOMElement $cell): array
    {
        $sources = [
            [$xpath, $this->first($xpath, 'w:tcPr/w:tcMar', $cell)],
            [$xpath, $this->first($xpath, 'w:tblPr/w:tblCellMar', $table)],
        ];

        $styleRef = $this->first($xpath, 'w:tblPr/w:tblStyle', $table);
        $style = $styleRef !== null ? ($this->tableStyles[$this->attr($styleRef, 'val')] ?? null) : $this->defaultTableStyle;
        for ($depth = 0; $style !== null && $this->stylesXpath !== null && $depth < 10; $depth++) {
            $sources[] = [$this->stylesXpath, $this->first($this->stylesXpath, 'w:tblPr/w:tblCellMar', $style)];
            $basedOn = $this->first($this->stylesXpath, 'w:basedOn', $style);
            $style = $basedOn !== null ? ($this->tableStyles[$this->attr($basedOn, 'val')] ?? null) : null;
        }
        if ($this->defaultTableStyle !== null && $this->stylesXpath !== null) {
            $sources[] = [$this->stylesXpath, $this->first($this->stylesXpath, 'w:tblPr/w:tblCellMar', $this->defaultTableStyle)];
        }

        $margins = [];
        foreach (['top' => ['top'], 'left' => ['left', 'start'], 'bottom' => ['bottom'], 'right' => ['right', 'end']] as $side => $names) {
            $margins[$side] = self::DEFAULT_MARGINS[$side];
            foreach ($sources as [$sourceXpath, $container]) {
                $value = $container !== null ? $this->marginValue($sourceXpath, $container, $names) : null;
                if ($value !== null) {
                    $margins[$side] = $value;

                    break;
                }
            }
        }

        return $margins;
    }

    /**
     * @param  array<int, string>  $names
     */
    private function marginValue(\DOMXPath $xpath, \DOMElement $container, array $names): ?int
    {
        foreach ($names as $name) {
            $node = $this->first($xpath, 'w:'.$name, $container);
            if ($node === null) {
                continue;
            }

            return $this->attr($node, 'type') === 'nil' ? 0 : max(0, (int) $this->attr($node, 'w'));
        }

        return null;
    }

    /**
     * Height available on the page: page height less top/bottom margins.
     */
    private function printableHeight(\DOMXPath $xpath): int
    {
        $size = $this->first($xpath, '//w:body/w:sectPr/w:pgSz');
        $margin = $this->first($xpath, '//w:body/w:sectPr/w:pgMar');
        if ($size === null) {
            return self::DEFAULT_MAX_HEIGHT;
        }

        $height = (int) $this->attr($size, 'h')
            - ($margin !== null ? abs((int) $this->attr($margin, 'top')) + abs((int) $this->attr($margin, 'bottom')) : 0);

        return $height > 0 ? $height : self::DEFAULT_MAX_HEIGHT;
    }

    /**
     * Drop paragraph spacing and exact line height from a picture-only
     * paragraph, so the picture fills its cell instead of being pushed out
     * of (or clipped by) a fixed-height row.
     */
    private function tightenParagraph(\DOMElement $paragraph): void
    {
        $dom = $paragraph->ownerDocument;
        $properties = $this->child($paragraph, 'pPr');
        if ($properties === null) {
            $properties = $dom->createElementNS(self::W_NS, 'w:pPr');
            $paragraph->insertBefore($properties, $paragraph->firstChild);
        }

        $spacing = $this->child($properties, 'spacing');
        if ($spacing === null) {
            $spacing = $dom->createElementNS(self::W_NS, 'w:spacing');
            $next = null;
            foreach ($properties->childNodes as $child) {
                if ($child instanceof \DOMElement && in_array($child->localName, self::PPR_AFTER_SPACING, true)) {
                    $next = $child;

                    break;
                }
            }
            $properties->insertBefore($spacing, $next);
        }

        foreach (['beforeLines', 'afterLines', 'beforeAutospacing', 'afterAutospacing'] as $attribute) {
            $spacing->removeAttributeNS(self::W_NS, $attribute);
        }
        $spacing->setAttributeNS(self::W_NS, 'w:before', '0');
        $spacing->setAttributeNS(self::W_NS, 'w:after', '0');
        if ($this->attr($spacing, 'lineRule') === 'exact') {
            $spacing->setAttributeNS(self::W_NS, 'w:line', '240');
            $spacing->setAttributeNS(self::W_NS, 'w:lineRule', 'auto');
        }
    }

    private function appendRun(\DOMElement $paragraph, string $text): void
    {
        $dom = $paragraph->ownerDocument;
        $run = $dom->createElementNS(self::W_NS, 'w:r');
        $node = $dom->createElementNS(self::W_NS, 'w:t');
        $node->appendChild($dom->createTextNode($text));
        $run->appendChild($node);
        $paragraph->appendChild($run);
    }

    /**
     * The cell's paragraph at an index (its last when out of range; a new
     * one when the cell has none).
     */
    private function cellParagraph(\DOMElement $cell, int $index): \DOMElement
    {
        $paragraphs = $this->paragraphs($cell);
        if ($paragraphs !== []) {
            return $paragraphs[min($index, count($paragraphs) - 1)];
        }

        $paragraph = $cell->ownerDocument->createElementNS(self::W_NS, 'w:p');
        $cell->appendChild($paragraph);

        return $paragraph;
    }

    private function paragraphIndex(\DOMElement $cell, \DOMElement $paragraph): int
    {
        $index = array_search($paragraph, $this->paragraphs($cell), true);

        return $index === false ? 0 : (int) $index;
    }

    /**
     * Paragraphs that belong to the cell itself (not to a nested table).
     *
     * @return array<int, \DOMElement>
     */
    private function paragraphs(\DOMElement $cell): array
    {
        $found = [];
        foreach ($cell->getElementsByTagNameNS(self::W_NS, 'p') as $paragraph) {
            if ($this->ancestor($paragraph, 'tc') === $cell) {
                $found[] = $paragraph;
            }
        }

        return $found;
    }

    /**
     * The row's own cells, in order (looking through content controls).
     *
     * @return array<int, \DOMElement>
     */
    private function rowCells(\DOMElement $row): array
    {
        $cells = [];
        foreach ($row->getElementsByTagNameNS(self::W_NS, 'tc') as $cell) {
            if ($this->ancestor($cell, 'tr') === $row) {
                $cells[] = $cell;
            }
        }

        return $cells;
    }

    private function span(\DOMXPath $xpath, \DOMElement $cell): int
    {
        $span = $this->first($xpath, 'w:tcPr/w:gridSpan', $cell);

        return $span !== null ? max(1, (int) $this->attr($span, 'val')) : 1;
    }

    private function firstTextContaining(\DOMXPath $xpath, string $token): ?\DOMElement
    {
        foreach ($xpath->query('//w:t') ?: [] as $node) {
            if (str_contains($node->textContent, $token)) {
                /** @var \DOMElement $node */
                return $node;
            }
        }

        return null;
    }

    private function ancestor(\DOMNode $node, string $localName): ?\DOMElement
    {
        for ($parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            if ($parent->namespaceURI === self::W_NS && $parent->localName === $localName) {
                return $parent;
            }
        }

        return null;
    }

    private function child(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::W_NS && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function first(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): ?\DOMElement
    {
        $node = ($context !== null ? $xpath->query($query, $context) : $xpath->query($query))?->item(0);

        return $node instanceof \DOMElement ? $node : null;
    }

    private function attr(\DOMElement $element, string $name): string
    {
        return $element->getAttributeNS(self::W_NS, $name);
    }

    private function uniqueName(string $name): string
    {
        return $name.'__img'.(++$this->counter);
    }

    private function replaceFirst(string $haystack, string $needle, string $replacement): string
    {
        $position = strpos($haystack, $needle);

        return $position === false
            ? $haystack
            : substr_replace($haystack, $replacement, $position, strlen($needle));
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function points(float $twips): string
    {
        return rtrim(rtrim(number_format($twips / 20, 2, '.', ''), '0'), '.').'pt';
    }
}
