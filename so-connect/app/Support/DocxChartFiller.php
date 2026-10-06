<?php

namespace App\Support;

use App\Helpers\FormTemplateHelper;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

/**
 * Fills the charts of a generated `.docx` from a Table field's rows.
 *
 * The Step-2 palette's "New chart" button inserts a native chart whose series
 * names and category are repeating column tokens (`{{expenses.amount#}}`,
 * `{{expenses.item#}}`). Those live in the chart part, which the template
 * processor never sees, so this runs on the populated file afterwards and
 * rebuilds each such chart's data the way the table itself is laid out:
 *
 *  - the embedded workbook (what "Edit chart data" opens) becomes the table —
 *    a header row of column labels, then one spreadsheet row per table row;
 *  - every token series is pointed at its column of that sheet, with its name
 *    set to the column label and its values/categories cached per row, which
 *    is what the editor and the PDF converter draw from.
 *
 * Charts without tokens are left untouched.
 */
class DocxChartFiller
{
    private const NS_C = 'http://schemas.openxmlformats.org/drawingml/2006/chart';

    private const NS_REL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** Series children that follow the category/value slots in schema order. */
    private const TRAILING = 'c:smooth|c:shape|c:bubbleSize|c:bubble3D|c:extLst';

    /**
     * @param  array<string, mixed>  $values  normalized key => value (lists for table columns)
     * @param  array<string, array<string, string>>  $tables  table field key => [column key => label]
     */
    public function fill(string $docxPath, array $values, array $tables = []): void
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $tables = $this->normalizeTables($tables);

        try {
            $charts = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (preg_match('#^word/charts/chart[^/]*\.xml$#', $name)) {
                    $charts[] = $name;
                }
            }

            foreach ($charts as $name) {
                $xml = $zip->getFromName($name);
                if (! is_string($xml) || ! str_contains($xml, '{{')) {
                    continue;
                }

                $filled = $this->fillChart($xml, $values, $tables);
                if ($filled === null) {
                    continue;
                }

                $zip->addFromString($name, $filled['xml']);

                $workbook = $this->embeddedWorkbook($zip, $name);
                if ($workbook !== null) {
                    $zip->addFromString($workbook, $this->workbook($filled['sheet'], $filled['columns']));
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, array{label: string, columns: array<string, string>}>  $tables
     * @return array{xml: string, sheet: string, columns: array<int, array{label: string, values: array<int, string>, numeric: bool}>}|null
     */
    private function fillChart(string $xml, array $values, array $tables): ?array
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            return null;
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('c', self::NS_C);

        $bound = [];
        $category = null;
        foreach ($xpath->query('//c:ser') as $ser) {
            /** @var DOMElement $ser */
            $name = $this->token($this->firstText($xpath, $ser, 'c:tx'));
            $cat = $this->token($this->firstText($xpath, $ser, 'c:cat|c:xVal'));
            $category ??= $cat;
            if ($name !== null) {
                $bound[] = ['ser' => $ser, 'key' => $name];
            }
        }

        if ($bound === []) {
            return null;
        }

        $sheet = $this->sheetName($xpath);

        // The sheet's columns: the whole table when the tokens belong to a
        // known Table field, plus any bound column it doesn't declare.
        $table = $tables[$this->normalize($this->tableKey($category ?? $bound[0]['key']))] ?? null;
        $columns = [];
        foreach ($table['columns'] ?? [] as $columnKey => $label) {
            $columns[$columnKey] = $label;
        }
        foreach (array_merge([$category], array_column($bound, 'key')) as $key) {
            if ($key !== null && ! array_key_exists($this->normalize($key), $columns)) {
                $columns[$this->normalize($key)] = Str::headline(Str::afterLast($key, '.'));
            }
        }

        $categoryKey = $category !== null ? $this->normalize($category) : null;
        $seriesKeys = array_map(fn (array $b) => $this->normalize($b['key']), $bound);

        $rows = 0;
        $data = [];
        foreach ($columns as $key => $label) {
            $data[$key] = $this->listValues($values[$key] ?? null);
            $rows = max($rows, count($data[$key]));
        }

        // Without a category token the rows are simply numbered.
        if ($categoryKey === null) {
            $categoryKey = '#';
            $columns = ['#' => 'No.'] + $columns;
            $data = ['#' => $rows > 0 ? array_map('strval', range(1, $rows)) : []] + $data;
        }

        $letters = [];
        $sheetColumns = [];
        foreach (array_keys($columns) as $index => $key) {
            $letters[$key] = Coordinate::stringFromColumnIndex($index + 1);
            $sheetColumns[] = [
                'label' => $columns[$key],
                'values' => array_pad($data[$key], $rows, ''),
                'numeric' => in_array($key, $seriesKeys, true),
            ];
        }

        $ref = $this->quoteSheet($sheet).'!';
        $last = max($rows + 1, 2);
        $categoryRange = $ref.'$'.$letters[$categoryKey].'$2:$'.$letters[$categoryKey].'$'.$last;

        foreach ($bound as $i => $b) {
            $key = $seriesKeys[$i];
            $letter = $letters[$key];

            $this->replaceChild($dom, $xpath, $b['ser'], ['c:tx'], '', $this->strRef(
                $dom, $ref.'$'.$letter.'$1', [$columns[$key]],
            ));
            $this->replaceChild($dom, $xpath, $b['ser'], ['c:cat', 'c:xVal'], 'c:val|c:yVal|'.self::TRAILING, $this->strRef(
                $dom, $categoryRange, array_pad($data[$categoryKey], $rows, ''),
            ));
            $this->replaceChild($dom, $xpath, $b['ser'], ['c:val', 'c:yVal'], self::TRAILING, $this->numRef(
                $dom, $ref.'$'.$letter.'$2:$'.$letter.'$'.$last, array_pad($data[$key], $rows, ''),
            ));
        }

        return ['xml' => (string) $dom->saveXML(), 'sheet' => $sheet, 'columns' => $sheetColumns];
    }

    /**
     * Replace (or create, for the series' category/value slots) the first of
     * `$names` under `$ser` with a fresh element holding `$ref`. A created slot
     * goes before the first `$before` sibling, keeping the schema order.
     *
     * @param  array<int, string>  $names
     */
    private function replaceChild(DOMDocument $dom, DOMXPath $xpath, DOMElement $ser, array $names, string $before, DOMElement $ref): void
    {
        $existing = null;
        foreach ($names as $name) {
            $existing = $xpath->query($name, $ser)->item(0);
            if ($existing instanceof DOMElement) {
                break;
            }
        }

        $wrapper = $dom->createElementNS(self::NS_C, $existing?->nodeName ?? $names[0]);
        $wrapper->appendChild($ref);

        if ($existing instanceof DOMElement) {
            $ser->replaceChild($wrapper, $existing);

            return;
        }

        $next = $before !== '' ? $xpath->query($before, $ser)->item(0) : null;
        $next ? $ser->insertBefore($wrapper, $next) : $ser->appendChild($wrapper);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function strRef(DOMDocument $dom, string $formula, array $values): DOMElement
    {
        $ref = $dom->createElementNS(self::NS_C, 'c:strRef');
        $ref->appendChild($this->textElement($dom, 'c:f', $formula));
        $cache = $dom->createElementNS(self::NS_C, 'c:strCache');
        $this->points($dom, $cache, $values, false);
        $ref->appendChild($cache);

        return $ref;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function numRef(DOMDocument $dom, string $formula, array $values): DOMElement
    {
        $ref = $dom->createElementNS(self::NS_C, 'c:numRef');
        $ref->appendChild($this->textElement($dom, 'c:f', $formula));
        $cache = $dom->createElementNS(self::NS_C, 'c:numCache');
        $cache->appendChild($this->textElement($dom, 'c:formatCode', 'General'));
        $this->points($dom, $cache, $values, true);
        $ref->appendChild($cache);

        return $ref;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function points(DOMDocument $dom, DOMElement $cache, array $values, bool $numeric): void
    {
        $count = $dom->createElementNS(self::NS_C, 'c:ptCount');
        $count->setAttribute('val', (string) count($values));
        $cache->appendChild($count);

        foreach (array_values($values) as $index => $value) {
            if ($numeric) {
                $value = $this->number($value);
                // A blank/non-numeric cell is a gap, not a zero.
                if ($value === null) {
                    continue;
                }
            }

            $pt = $dom->createElementNS(self::NS_C, 'c:pt');
            $pt->setAttribute('idx', (string) $index);
            $pt->appendChild($this->textElement($dom, 'c:v', $value));
            $cache->appendChild($pt);
        }
    }

    private function textElement(DOMDocument $dom, string $name, string $text): DOMElement
    {
        $element = $dom->createElementNS(self::NS_C, $name);
        $element->appendChild($dom->createTextNode($text));

        return $element;
    }

    /**
     * The embedded workbook part behind a chart, if any.
     */
    private function embeddedWorkbook(ZipArchive $zip, string $chart): ?string
    {
        $rels = $zip->getFromName(dirname($chart).'/_rels/'.basename($chart).'.rels');
        if (! is_string($rels) || $rels === '') {
            return null;
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($rels)) {
            return null;
        }

        foreach ($dom->getElementsByTagNameNS(self::NS_REL, 'Relationship') as $rel) {
            /** @var DOMElement $rel */
            $target = $rel->getAttribute('Target');
            if (strtolower($rel->getAttribute('TargetMode')) === 'external' || ! str_ends_with(strtolower($target), '.xlsx')) {
                continue;
            }

            $path = $this->resolvePath(dirname($chart), $target);
            if ($zip->locateName($path) !== false) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{label: string, values: array<int, string>, numeric: bool}>  $columns
     */
    private function workbook(string $sheetName, array $columns): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $sheetName) ?: 'Sheet1', 31, ''));

        foreach ($columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValueExplicit($letter.'1', $column['label'], DataType::TYPE_STRING);
            foreach ($column['values'] as $row => $value) {
                $cell = $letter.($row + 2);
                $number = $column['numeric'] ? $this->number($value) : null;
                $number !== null
                    ? $sheet->setCellValueExplicit($cell, (float) $number, DataType::TYPE_NUMERIC)
                    : $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'chart');
        try {
            (new Xlsx($spreadsheet))->save($path);

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function firstText(DOMXPath $xpath, DOMElement $ser, string $path): ?string
    {
        $node = $xpath->query('('.$this->descendants($path).')[1]', $ser)->item(0);

        return $node?->textContent;
    }

    private function descendants(string $path): string
    {
        return implode('|', array_map(fn (string $p) => $p.'//c:v', explode('|', $path)));
    }

    /**
     * The field key a chart cell token names (`{{key#}}` → `key`), if any.
     */
    private function token(?string $text): ?string
    {
        if ($text === null || ! preg_match('/^\s*\{\{\s*([^{}]+?)\s*\}\}\s*$/', $text, $m)) {
            return null;
        }
        $key = trim(rtrim($m[1], '#'));

        return $key !== '' ? $key : null;
    }

    /**
     * The sheet the chart's existing formulas point at (OnlyOffice and Word
     * both default to `Sheet1`).
     */
    private function sheetName(DOMXPath $xpath): string
    {
        $formula = $xpath->query('//c:f')->item(0)?->textContent ?? '';
        if (preg_match("/^'?(.+?)'?!/", $formula, $m)) {
            return str_replace("''", "'", $m[1]);
        }

        return 'Sheet1';
    }

    private function quoteSheet(string $sheet): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $sheet) ? $sheet : "'".str_replace("'", "''", $sheet)."'";
    }

    private function tableKey(string $columnKey): string
    {
        return str_contains($columnKey, '.') ? Str::beforeLast($columnKey, '.') : $columnKey;
    }

    private function normalize(string $key): string
    {
        return FormTemplateHelper::normalizeFieldKey($key);
    }

    /**
     * @param  array<string, array<string, string>>  $tables
     * @return array<string, array{columns: array<string, string>}>
     */
    private function normalizeTables(array $tables): array
    {
        $normalized = [];
        foreach ($tables as $table => $columns) {
            $entry = ['columns' => []];
            foreach ((array) $columns as $column => $label) {
                $entry['columns'][$this->normalize($table.'.'.$column)] = (string) $label;
            }
            $normalized[$this->normalize((string) $table)] = $entry;
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function listValues(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_map(
            fn ($item) => is_scalar($item) ? (string) $item : '',
            is_array($value) ? $value : [$value],
        ));
    }

    /**
     * A cell as a plain number string, tolerating thousands separators and
     * currency marks (`₱1,500.50` → `1500.50`).
     */
    private function number(string $value): ?string
    {
        $clean = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

        return $clean !== '' && is_numeric($clean) ? $clean : null;
    }

    private function resolvePath(string $base, string $target): string
    {
        $parts = str_starts_with($target, '/') ? [] : explode('/', $base);
        foreach (explode('/', ltrim($target, '/')) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }
}
