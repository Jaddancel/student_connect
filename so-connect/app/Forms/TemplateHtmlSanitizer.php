<?php

namespace App\Forms;

/**
 * Server-side sanitizer for printed-PDF template HTML.
 *
 * The Step 2 rich-text editor emits a small, known-good subset of HTML; this
 * reduces any incoming markup (editor output or imported .docx) to that subset
 * so only dompdf-safe tags/attributes are ever persisted or rendered. It is the
 * authoritative counterpart to the editor's best-effort client-side scrub.
 */
final class TemplateHtmlSanitizer
{
    /** Tags kept as-is (everything else is unwrapped to its text/children). */
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li', 'span', 'div',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'colgroup', 'col',
    ];

    /** Inline style properties kept on any element (alignment + font + tables). */
    private const ALLOWED_STYLE_PROPS = [
        'text-align', 'font-size', 'font-family', 'width', 'height',
        'border', 'border-width', 'border-style', 'border-color', 'border-collapse',
        'padding', 'background-color', 'vertical-align',
    ];

    /** Tags on which colspan/rowspan (integer) attributes are preserved. */
    private const SPAN_TAGS = ['td', 'th'];

    /** Tags dropped entirely, contents and all (e.g. leaked <style>/<script>). */
    private const DROP_TAGS = [
        'style', 'script', 'head', 'title', 'meta', 'link', 'base',
    ];

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="sanitize-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $root = $dom->getElementById('sanitize-root');
        if ($root === null) {
            return '';
        }

        self::scrub($dom, $root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function scrub(\DOMDocument $dom, \DOMNode $node): void
    {
        // Iterate over a static snapshot; we mutate children in place.
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }
            if (! $child instanceof \DOMElement) {
                // Comments / processing instructions — drop.
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_TAGS, true)) {
                $node->removeChild($child);
                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap: recurse first, then splice children in place of the node.
                self::scrub($dom, $child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::filterAttributes($child, $tag);
            self::scrub($dom, $child);
        }
    }

    private static function filterAttributes(\DOMElement $el, string $tag): void
    {
        $attrs = [];
        foreach ($el->attributes as $attr) {
            $attrs[] = $attr->nodeName;
        }

        foreach ($attrs as $name) {
            $lower = strtolower($name);

            if ($lower === 'data-field' && $tag === 'span') {
                continue;
            }
            if ($lower === 'data-universal' && $tag === 'span') {
                continue;
            }
            if ($lower === 'contenteditable' && $tag === 'span') {
                continue;
            }
            // Repeating-row token markers + table cell binding.
            if ($lower === 'data-field-rows' && in_array($tag, ['tbody', 'thead', 'table'], true)) {
                continue;
            }
            if ($lower === 'data-col' && in_array($tag, self::SPAN_TAGS, true)) {
                continue;
            }
            // Table cell spans (positive integers only).
            if (($lower === 'colspan' || $lower === 'rowspan')
                && in_array($tag, self::SPAN_TAGS, true)
                && preg_match('/^[1-9][0-9]?$/', (string) $el->getAttribute($name))) {
                continue;
            }
            if ($lower === 'class' && $tag === 'span') {
                $classes = preg_split('/\s+/', (string) $el->getAttribute('class')) ?: [];
                $keep = array_values(array_intersect($classes, ['field-token', 'field-token--unmapped']));
                if ($keep) {
                    $el->setAttribute('class', implode(' ', $keep));
                } else {
                    $el->removeAttribute($name);
                }
                continue;
            }
            if ($lower === 'style') {
                $clean = self::sanitizeStyle((string) $el->getAttribute('style'));
                if ($clean !== null) {
                    $el->setAttribute('style', $clean);
                } else {
                    $el->removeAttribute($name);
                }
                continue;
            }

            $el->removeAttribute($name);
        }
    }

    /**
     * Keep only a safe subset of inline CSS: text alignment and font styling.
     * Blocks anything with url()/expression()/javascript: and strips unexpected
     * characters from values.
     */
    private static function sanitizeStyle(string $style): ?string
    {
        $out = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }
            [$prop, $value] = explode(':', $declaration, 2);
            $prop = strtolower(trim($prop));
            $value = trim($value);

            if (! in_array($prop, self::ALLOWED_STYLE_PROPS, true) || $value === '') {
                continue;
            }
            if (preg_match('/url\(|expression|javascript:/i', $value)) {
                continue;
            }
            // Allow #hex colours and rgb() for background/border colours.
            $value = (string) preg_replace('/[^a-zA-Z0-9 ,.\'"%#()\-]/', '', $value);
            if ($value === '') {
                continue;
            }
            $out[] = $prop.': '.$value;
        }

        return $out ? implode('; ', $out) : null;
    }
}
