<?php

namespace App\Services;

use App\Helpers\FormTemplateHelper;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Derives a web-form field list from a DOCX.
 *
 * The canonical template format marks every field with a {{placeholder}}
 * (or {{placeholder#}} for a multiline/textarea field), so the parser first
 * extracts those directly — this is exact and is always preferred when any
 * placeholder is present.
 *
 * If the DOCX contains no placeholders (a genuinely blank form), it falls back
 * to layout heuristics that detect labelled inputs:
 *
 *   - "Label:" paragraphs, or labels trailed by an underline run        → text
 *   - Two-column table rows (label | blank cell)                        → text
 *   - Cells/paragraphs containing ☐ ☑ ☒ or a w:sym checkbox glyph        → checkbox
 *   - Paragraphs that are mostly a long blank underline with no label    → textarea
 */
class DocxFormStructureParser
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const CHECKBOX_GLYPHS = ['☐', '☑', '☒', '◻', '❑', ''];

    /**
     * @return array<int, array{
     *   field_label: string,
     *   field_key: string,
     *   field_type: string,
     *   is_required: bool,
     *   field_order: int,
     *   field_options: array<int, string>|null
     * }>
     */
    public function parse(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException('DOCX file does not exist: '.$absolutePath);
        }

        // Primary path: the template marks every field with a {{placeholder}}.
        // This is exact, so prefer it whenever any placeholder is present.
        $placeholderFields = $this->extractPlaceholderFields($absolutePath);
        if (! empty($placeholderFields)) {
            return $placeholderFields;
        }

        // Fallback: a genuinely blank DOCX with no placeholders — guess from layout.
        return $this->parseByLayoutHeuristics($absolutePath);
    }

    /**
     * Exact extraction of {{field_key}} placeholders in document order, with the
     * field type inferred from both the placeholder name and its surroundings:
     *
     *   - a key that names an image (signature, photo, picture, …)  → file
     *   - a placeholder inside a <w:tbl>                            → row
     *   - a trailing # ({{field_key#}})                            → textarea
     *   - anything else                                            → text
     *
     * Any inline label text preceding a placeholder on the same line becomes the
     * field label; otherwise the key is humanised (e.g. "first_name" → "First Name").
     * A DOM walk is used (rather than raw-XML scanning) so that table membership is
     * known; concatenating a paragraph's <w:t> nodes reconstructs placeholders that
     * Word has split across runs, mirroring FormTemplateHelper.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractPlaceholderFields(string $absolutePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('Unable to read DOCX file.');
        }

        // Read the body first, then any headers/footers, so body fields keep
        // their natural order and win when a key is duplicated elsewhere.
        $entryNames = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name)) {
                continue;
            }
            if ($name === 'word/document.xml'
                || preg_match('/^word\/(header|footer)\d*\.xml$/', $name)) {
                $entryNames[] = $name;
            }
        }
        usort($entryNames, fn ($a, $b) => ($a === 'word/document.xml' ? -1 : 1) <=> ($b === 'word/document.xml' ? -1 : 1));

        $fields = [];
        $usedKeys = [];

        foreach ($entryNames as $name) {
            $content = $zip->getFromName($name);
            if (! is_string($content) || $content === '') {
                continue;
            }

            $dom = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($content);
            libxml_use_internal_errors($previous);
            if (! $loaded) {
                continue;
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', self::W_NS);

            foreach ($xpath->query('//w:p') as $paragraph) {
                if (! $paragraph instanceof DOMElement) {
                    continue;
                }

                $inTable = $xpath->query('ancestor::w:tbl', $paragraph)->length > 0;
                $paraText = $this->elementText($paragraph, $xpath);

                foreach ($this->placeholdersInParagraph($paraText) as [$rawKey, $label]) {
                    $isMultiline = str_ends_with($rawKey, '#');
                    $baseKey = $isMultiline ? substr($rawKey, 0, -1) : $rawKey;
                    $key = FormTemplateHelper::normalizeFieldKey($baseKey);

                    if ($key === '' || isset($usedKeys[$key])) {
                        continue;
                    }
                    $usedKeys[$key] = true;

                    $fields[] = [
                        'field_label' => $label !== '' ? $label : $this->humanizeKey($key),
                        'field_key' => $key,
                        'field_type' => $this->resolveFieldType($key, $inTable, $isMultiline),
                        'is_required' => false,
                        'field_order' => count($fields) + 1,
                        'field_options' => null,
                    ];
                }
            }
        }

        $zip->close();

        return $fields;
    }

    /**
     * Decide a field's type from its key and its position in the document.
     * Photo detection wins over table membership (a signature image in a table
     * cell is still an uploaded file, not a repeating text row).
     */
    private function resolveFieldType(string $key, bool $inTable, bool $isMultiline): string
    {
        if ($this->looksLikePhotoKey($key)) {
            return 'file';
        }

        if ($inTable) {
            return 'row';
        }

        return $isMultiline ? 'textarea' : 'text';
    }

    /**
     * True when the key names an uploaded image: signatures, profile/ID photos,
     * documentation scans, logos, etc. Keys are underscore-delimited, so these
     * substrings match e.g. id_photo_front, profile_photo, signature_image.
     */
    private function looksLikePhotoKey(string $key): bool
    {
        return (bool) preg_match(
            '/(signature|photo|picture|image|headshot|logo|avatar|documentation)/',
            $key
        );
    }

    /**
     * Find every {{placeholder}} in a paragraph along with the label text that
     * immediately precedes it on the same line.
     *
     * @return array<int, array{0: string, 1: string}>  [rawKey (may end in #), label]
     */
    private function placeholdersInParagraph(string $paraText): array
    {
        $regex = '/\{\{\s*([a-zA-Z0-9_.:\-]+#?)\s*\}\}/';
        $parts = preg_split($regex, $paraText, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return [];
        }

        $out = [];
        // preg_split with capture yields: text, key, text, key, … — keys at odd indexes.
        for ($i = 1; $i < count($parts); $i += 2) {
            $out[] = [$parts[$i], $this->cleanLabel($parts[$i - 1] ?? '')];
        }

        return $out;
    }

    /**
     * Turn the text preceding a placeholder into a clean label, or '' if there
     * is nothing usable (caller then falls back to the humanised key).
     */
    private function cleanLabel(string $text): string
    {
        // When several fields share a line, keep only the segment after the last
        // tab — that's the label nearest this placeholder.
        if (str_contains($text, "\t")) {
            $segments = explode("\t", $text);
            $text = (string) end($segments);
        }

        $text = trim($text);
        $text = rtrim($text, " \t:_-");          // drop trailing colons / fill lines
        $text = ltrim($text, " \t-•:");          // drop leading bullets / glyphs
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($text === '' || mb_strlen($text) > 60) {
            return '';
        }

        return $text;
    }

    /**
     * "first_name" → "First Name", "present_address" → "Present Address".
     */
    private function humanizeKey(string $key): string
    {
        $words = array_filter(preg_split('/[_\s]+/', $key) ?: [], fn ($w) => $w !== '');

        return ucwords(implode(' ', $words));
    }

    /**
     * @return array<int, array{
     *   field_label: string,
     *   field_key: string,
     *   field_type: string,
     *   is_required: bool,
     *   field_order: int,
     *   field_options: array<int, string>|null
     * }>
     */
    private function parseByLayoutHeuristics(string $absolutePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('Unable to read DOCX file.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($documentXml) || $documentXml === '') {
            return [];
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($documentXml);
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('DOCX document.xml could not be parsed.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::W_NS);

        $body = $xpath->query('//w:body')->item(0);
        if (! $body instanceof DOMElement) {
            return [];
        }

        $fields = [];
        $usedKeys = [];

        // Walk the body's direct children in document order so paragraphs and
        // tables are processed exactly as they appear in the form.
        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            if ($node->localName === 'p') {
                $detected = $this->detectFromParagraph($node, $xpath);
            } elseif ($node->localName === 'tbl') {
                $detected = $this->detectFromTable($node, $xpath);
            } else {
                continue;
            }

            foreach ($detected as $field) {
                $field['field_key'] = $this->uniqueKey($field['field_label'], $usedKeys);
                $field['field_order'] = count($fields) + 1;
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectFromParagraph(DOMElement $paragraph, DOMXPath $xpath): array
    {
        $text = $this->elementText($paragraph, $xpath);
        $hasCheckboxGlyph = $this->containsCheckboxGlyph($text)
            || $xpath->query('.//w:sym', $paragraph)->length > 0
            || $xpath->query('.//w:checkBox', $paragraph)->length > 0;

        $clean = trim($text);

        if ($hasCheckboxGlyph) {
            $label = $this->stripCheckboxGlyphs($clean);
            if ($label === '') {
                return [];
            }

            return [$this->makeField($label, 'checkbox')];
        }

        if ($clean === '') {
            return [];
        }

        // "Label:" or "Label: ____"
        if (preg_match('/^(.{1,80}?):\s*_*\s*$/u', $clean, $m)) {
            return [$this->makeField(trim($m[1]), 'text')];
        }

        // "Label ______" (label followed by an underline blank on the same line)
        if (preg_match('/^(.{1,80}?)\s_{3,}\s*$/u', $clean, $m)) {
            return [$this->makeField(trim($m[1]), 'text')];
        }

        // A line that is essentially just a long blank → free-text area.
        if (preg_match('/^_{6,}$/u', $clean)) {
            return [$this->makeField('Additional Details', 'textarea')];
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectFromTable(DOMElement $table, DOMXPath $xpath): array
    {
        $fields = [];

        foreach ($xpath->query('.//w:tr', $table) as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = $xpath->query('./w:tc', $row);
            $cellTexts = [];
            foreach ($cells as $cell) {
                if ($cell instanceof DOMElement) {
                    $cellTexts[] = trim($this->elementText($cell, $xpath));
                }
            }

            // Checkbox cell anywhere in the row.
            foreach ($cellTexts as $cellText) {
                if ($this->containsCheckboxGlyph($cellText)) {
                    $label = $this->stripCheckboxGlyphs($cellText);
                    if ($label !== '') {
                        $fields[] = $this->makeField($label, 'checkbox');
                    }
                }
            }

            // Two-column "label | blank" row → text field.
            if (count($cellTexts) === 2 && $cellTexts[0] !== '' && $cellTexts[1] === ''
                && ! $this->containsCheckboxGlyph($cellTexts[0])) {
                $fields[] = $this->makeField(rtrim($cellTexts[0], ':'), 'text');
            }
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeField(string $label, string $type): array
    {
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);

        return [
            'field_label' => $label,
            'field_key' => FormTemplateHelper::normalizeFieldKey($label),
            'field_type' => $type,
            'is_required' => false,
            'field_order' => 0,
            'field_options' => $type === 'checkbox' ? [$label] : null,
        ];
    }

    /**
     * @param  array<string, bool>  $usedKeys
     */
    private function uniqueKey(string $label, array &$usedKeys): string
    {
        $base = FormTemplateHelper::normalizeFieldKey($label);
        $key = $base;
        $i = 2;

        while (isset($usedKeys[$key])) {
            $key = $base.'_'.$i;
            $i++;
        }

        $usedKeys[$key] = true;

        return $key;
    }

    private function elementText(DOMElement $element, DOMXPath $xpath): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t', $element) as $textNode) {
            $parts[] = $textNode->textContent;
        }

        return html_entity_decode(implode('', $parts), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function containsCheckboxGlyph(string $text): bool
    {
        foreach (self::CHECKBOX_GLYPHS as $glyph) {
            if ($glyph !== '' && str_contains($text, $glyph)) {
                return true;
            }
        }

        return false;
    }

    private function stripCheckboxGlyphs(string $text): string
    {
        $text = str_replace(self::CHECKBOX_GLYPHS, ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
