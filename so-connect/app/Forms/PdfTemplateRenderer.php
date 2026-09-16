<?php

namespace App\Forms;

use App\Models\Form\FormDescription;
use App\Models\Organization;
use App\Models\Profile;
use App\Support\OrganizationField;
use App\Support\UniversalField;
use Illuminate\Support\Collection;

/**
 * Renders a printed-PDF template (rich-text HTML with inline field tokens) into
 * a finished HTML body by replacing each `<span data-field="{field_key}">` token
 * with the matching submission value.
 *
 * The template HTML is authored in the wizard's Step 2 and stored in
 * `forms.pdf_template.html`. Tokens carry no value of their own — they are the
 * single source of truth for *where* a field's answer prints; replacement here
 * resolves the answer by `field_key`, so renaming a field label never breaks a
 * template and a deleted field degrades to an empty placeholder rather than an
 * error.
 */
final class PdfTemplateRenderer
{
    /**
     * @param  array<string,mixed>  $payload  submission payload keyed by field_key
     * @param  Collection<int,FormDescription>  $fields
     * @param  ?Profile  $profile  submitter's profile, used to resolve profile-source
     *                             `data-universal` tokens; null renders them empty
     * @param  ?Organization  $organization  submitter's org, used to resolve
     *                             org-source `data-universal` tokens (president/…)
     */
    public function render(string $templateHtml, array $payload, Collection $fields, ?string $disk = null, ?Profile $profile = null, ?Organization $organization = null): string
    {
        $disk ??= (string) config('documents.disk', 'public');

        if (trim($templateHtml) === '') {
            return '';
        }

        /** @var array<string,FormDescription> $fieldsByKey */
        $fieldsByKey = $fields->keyBy('field_key')->all();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // Wrap so a document fragment parses predictably; force UTF-8.
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="pdf-template-root">'.$templateHtml.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Repeating table rows: a template row inside <... data-field-rows="key">
        // is cloned per submitted row, its data-col cells filled from the row.
        $this->expandRowTokens($dom, $xpath, $fieldsByKey, $payload);

        $tokens = $xpath->query('//span[@data-field]');

        if ($tokens !== false) {
            // Snapshot into an array first — we mutate the tree while iterating.
            $tokenNodes = [];
            foreach ($tokens as $node) {
                $tokenNodes[] = $node;
            }

            foreach ($tokenNodes as $token) {
                /** @var \DOMElement $token */
                $key = (string) $token->getAttribute('data-field');
                $field = $fieldsByKey[$key] ?? null;

                $replacement = $field
                    ? $this->replacementNodes($dom, $field, $payload, $disk)
                    : [$dom->createTextNode('')];

                $parent = $token->parentNode;
                if ($parent === null) {
                    continue;
                }
                foreach ($replacement as $newNode) {
                    $parent->insertBefore($newNode, $token);
                }
                $parent->removeChild($token);
            }
        }

        // Universal tokens print a value straight from the submitter's profile,
        // independent of whether the form has a matching field.
        $universalTokens = $xpath->query('//span[@data-universal]');
        if ($universalTokens !== false) {
            $universalNodes = [];
            foreach ($universalTokens as $node) {
                $universalNodes[] = $node;
            }

            foreach ($universalNodes as $token) {
                /** @var \DOMElement $token */
                $key = (string) $token->getAttribute('data-universal');
                $replacement = $this->universalNodes($dom, $profile, $organization, $key, $disk);

                $parent = $token->parentNode;
                if ($parent === null) {
                    continue;
                }
                foreach ($replacement as $newNode) {
                    $parent->insertBefore($newNode, $token);
                }
                $parent->removeChild($token);
            }
        }

        $root = $dom->getElementById('pdf-template-root');
        if ($root === null) {
            return $templateHtml;
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }

    /**
     * Expand repeating-row tokens: for each element carrying
     * `data-field-rows="{tableKey}"`, take its single template row (last element
     * child) and clone it once per submitted row, binding each descendant cell
     * marked `data-col="{columnKey}"` to that row's value.
     *
     * @param  array<string,FormDescription>  $fieldsByKey
     * @param  array<string,mixed>  $payload
     */
    private function expandRowTokens(\DOMDocument $dom, \DOMXPath $xpath, array $fieldsByKey, array $payload): void
    {
        $containers = $xpath->query('//*[@data-field-rows]');
        if ($containers === false) {
            return;
        }

        $containerNodes = [];
        foreach ($containers as $node) {
            $containerNodes[] = $node;
        }

        foreach ($containerNodes as $container) {
            /** @var \DOMElement $container */
            $key = (string) $container->getAttribute('data-field-rows');

            // The template row = the last element child (any earlier element
            // children, e.g. a header row, are kept as-is).
            $templateRow = null;
            foreach ($container->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $templateRow = $child;
                }
            }
            if ($templateRow === null) {
                continue;
            }

            $rows = $payload[$key] ?? [];
            $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];

            foreach ($rows as $row) {
                $clone = $templateRow->cloneNode(true);
                $cells = $xpath->query('.//*[@data-col]', $clone);
                if ($cells !== false) {
                    foreach ($cells as $cell) {
                        /** @var \DOMElement $cell */
                        $col = (string) $cell->getAttribute('data-col');
                        while ($cell->firstChild) {
                            $cell->removeChild($cell->firstChild);
                        }
                        $cell->appendChild($dom->createTextNode((string) ($row[$col] ?? '')));
                    }
                }
                $container->insertBefore($clone, $templateRow);
            }

            // Drop the (unfilled) template row.
            $container->removeChild($templateRow);
        }
    }

    /**
     * Build the DOM node(s) that replace a single field token.
     *
     * @param  array<string,mixed>  $payload
     * @return array<int,\DOMNode>
     */
    private function replacementNodes(\DOMDocument $dom, FormDescription $field, array $payload, string $disk): array
    {
        $key = (string) $field->field_key;
        $type = (string) $field->field_type;
        $options = (array) ($field->field_options ?? []);

        if (in_array($type, [FieldType::MULTI_IMAGE, FieldType::WAIVER_SCAN], true)) {
            $uris = SubmissionPresenter::imageDataUris($payload, $key, $disk);
            $nodes = [];
            foreach ($uris as $uri) {
                $img = $dom->createElement('img');
                $img->setAttribute('src', $uri);
                $img->setAttribute('class', 'token-image');
                $nodes[] = $img;
            }

            return $nodes ?: [$dom->createTextNode('')];
        }

        if (in_array($type, [FieldType::ORG_SELECT, FieldType::EVENT_SELECT, FieldType::WORKPLAN_EVENTS], true)) {
            return [$dom->createTextNode(SpecialFieldLabel::forField($type, $payload[$key] ?? null))];
        }

        if ($type === FieldType::PASSWORD) {
            // Never print a password (stored hashed anyway).
            return [$dom->createTextNode('')];
        }

        if ($type === FieldType::TEXT_LIST) {
            $items = array_filter(array_map('strval', (array) ($payload[$key] ?? [])), fn ($v) => $v !== '');

            return [$dom->createTextNode(implode(', ', $items))];
        }

        if (in_array($type, [FieldType::IMAGE, FieldType::SIGNATURE], true)) {
            $uris = SubmissionPresenter::imageDataUris($payload, $key, $disk);
            $nodes = [];
            foreach ($uris as $uri) {
                $img = $dom->createElement('img');
                $img->setAttribute('src', $uri);
                $img->setAttribute('class', 'token-image');
                $nodes[] = $img;
            }

            return $nodes ?: [$dom->createTextNode('')];
        }

        if ($type === FieldType::FILE) {
            $raw = SubmissionPresenter::raw($payload, $key);
            $names = [];
            foreach ((array) $raw as $path) {
                $path = (string) $path;
                if ($path !== '') {
                    $names[] = basename($path);
                }
            }

            return [$dom->createTextNode(implode(', ', $names))];
        }

        if ($type === FieldType::CHECKBOX) {
            $optionValues = FieldType::optionValues($options);

            // A lone checkmark prints a single ticked/empty box.
            if ($optionValues === []) {
                return [$this->checkboxNode($dom, SubmissionPresenter::isChecked($payload, $key))];
            }

            // A checkbox group prints one box per option, ticked when chosen,
            // each followed by its label.
            $selected = array_map('strval', (array) SubmissionPresenter::raw($payload, $key));
            $nodes = [];
            foreach (FieldType::optionPairs($options) as $i => $pair) {
                if ($i > 0) {
                    $nodes[] = $dom->createTextNode("\u{00A0}\u{00A0}");
                }
                $nodes[] = $this->checkboxNode($dom, in_array($pair['value'], $selected, true));
                $nodes[] = $dom->createTextNode("\u{00A0}".$pair['label']);
            }

            return $nodes ?: [$dom->createTextNode('')];
        }

        $text = SubmissionPresenter::display($payload, $key, $type, $options);

        return [$dom->createTextNode($text)];
    }

    /**
     * A printed checkbox: an inline SVG image drawing a square outline plus a
     * tick when checked. Rendered as an SVG data-URI (through dompdf's image
     * pipeline) rather than a Unicode ballot glyph or a text check mark, so it
     * prints identically regardless of the document font's glyph coverage
     * (the default Times family has no check-mark glyph).
     */
    private function checkboxNode(\DOMDocument $dom, bool $checked): \DOMElement
    {
        $tick = $checked
            ? '<path d="M2.2 5.2 L4.3 7.4 L7.9 2.6" fill="none" stroke="#111" '
                .'stroke-width="1.3" stroke-linecap="square" stroke-linejoin="miter"/>'
            : '';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 10 10">'
            .'<rect x="0.6" y="0.6" width="8.8" height="8.8" rx="1" fill="none" stroke="#111" stroke-width="1"/>'
            .$tick
            .'</svg>';

        $img = $dom->createElement('img');
        $img->setAttribute('src', 'data:image/svg+xml;base64,'.base64_encode($svg));
        $img->setAttribute('class', 'token-checkbox'.($checked ? ' is-checked' : ''));
        $img->setAttribute('style', 'width:10px;height:10px;vertical-align:middle;');
        $img->setAttribute('alt', $checked ? '[x]' : '[ ]');

        return $img;
    }

    /**
     * Build the DOM node(s) that replace a single universal token, resolving the
     * value off the submitter's profile. Image-typed universal fields (photos)
     * render as an <img>; everything else renders as text. Missing profile or
     * value degrades to an empty text node.
     *
     * @return array<int,\DOMNode>
     */
    private function universalNodes(\DOMDocument $dom, ?Profile $profile, ?Organization $organization, string $key, string $disk): array
    {
        $value = UniversalField::isOrgField($key)
            ? OrganizationField::value($organization, $key)
            : UniversalField::valueFor($profile, $key);
        if ($value === null || $value === '') {
            return [$dom->createTextNode('')];
        }

        $meta = UniversalField::get($key);
        if ($meta !== null && $meta['type'] === FieldType::IMAGE) {
            $uri = SubmissionPresenter::imageDataUris(['__u' => $value], '__u', $disk);
            if ($uri !== []) {
                $img = $dom->createElement('img');
                $img->setAttribute('src', $uri[0]);
                $img->setAttribute('class', 'token-image');

                return [$img];
            }

            return [$dom->createTextNode('')];
        }

        return [$dom->createTextNode((string) $value)];
    }
}
