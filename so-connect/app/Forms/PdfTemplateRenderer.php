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

        $text = SubmissionPresenter::display($payload, $key, $type, $options);

        return [$dom->createTextNode($text)];
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
