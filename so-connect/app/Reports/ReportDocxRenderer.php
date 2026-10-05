<?php

namespace App\Reports;

use App\Forms\DocxTemplateData;
use App\Models\Organization;
use App\Models\Template;
use App\Models\User;
use App\Services\DocxTemplateService;
use App\Support\DocxTemplateProcessor;
use App\Support\UniversalField;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Prints a report's data tree into one of its .docx template slots.
 *
 * Template syntax (on top of the form builder's `{{key}}` / `{{key#}}`):
 *
 *  - `{{#P}} … {{/P}}` — a repeating block for the group at dotted path P
 *    (`orgs`, `orgs.officers`). Markers sit in their own paragraphs (or both in
 *    one paragraph, which then repeats as a whole). Everything between them is
 *    cloned once per row; inside the clone for row i, tokens under P are
 *    re-keyed to that row (`{{P.name}}` → `{{P__i.name}}`), so nested blocks
 *    and nested table rows bind to the right parent.
 *  - `{{P.child#}}` in a table row — the row repeats once per row of P (the
 *    stock cloneRow machinery), e.g. officers of the current organization when
 *    used inside an `{{#orgs}}` block.
 *  - `{{profile.*}}` / `{{system.*}}` — universal tokens.
 *
 * Block expansion runs on the main document part after PhpWord's split-run
 * repair; the expanded document is then filled by {@see DocxTemplateService}.
 */
final class ReportDocxRenderer
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Separator between a group path and a row index in re-keyed tokens. */
    public const INDEX_SEPARATOR = '__';

    public function __construct(private readonly DocxTemplateService $docx) {}

    /**
     * Render a slot; returns the absolute path of the populated .docx (in a
     * scratch directory the caller deletes).
     *
     * @param  array<string, mixed>  $data  the engine's data tree
     */
    public function render(Template $slot, array $data, ?User $user = null, ?Organization $organization = null): string
    {
        $disk = (string) config('documents.disk', 'public');
        $source = Storage::disk($disk)->path((string) $slot->docx_path);
        if (! is_file($source)) {
            throw new \RuntimeException('Report template "'.$slot->template_name.'" has no .docx file on disk.');
        }

        [$values, $groups] = $this->flatten($data);
        $universal = $this->universal($user, $organization, $disk);
        $values = $universal['values'] + $values;

        $relative = 'report-tmp/'.Str::uuid().'.docx';
        $expanded = Storage::disk($disk)->path($relative);
        File::ensureDirectoryExists(dirname($expanded));

        try {
            $processor = new ReportTemplateProcessor($source);
            $processor->setMainPart($this->expandBlocks($processor->mainPart(), $groups));
            $processor->saveAs($expanded);
        } finally {
            DocxTemplateProcessor::resetMacroChars();
        }

        try {
            $scratch = new Template([
                'template_name' => (string) $slot->template_name,
                'docx_path' => $relative,
            ]);
            $scratch->id = $slot->getKey();

            return $this->docx->populate($scratch, $values, $universal['images']);
        } finally {
            Storage::disk($disk)->delete($relative);
        }
    }

    /**
     * Flatten the data tree into placeholder values plus a registry of group
     * rows (by dotted path) for block expansion.
     *
     * For a group at path P with rows r1…rn and value child c:
     *   - `P.c`       => [r1.c, …, rn.c]  (table-row repeat / joined list)
     *   - `P__i.c`    => ri.c              (inside the i-th cloned block)
     * Nested groups recurse at `P__i.child` (and `P.child` across all rows).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, array<int, array<string, mixed>>>}
     */
    public function flatten(array $data): array
    {
        $values = [];
        $groups = [];

        foreach ($data as $name => $value) {
            if (is_array($value)) {
                $this->flattenGroup((string) $name, $value, $values, $groups);
            } else {
                $values[(string) $name] = (string) $value;
            }
        }

        return [$values, $groups];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $values
     * @param  array<string, array<int, array<string, mixed>>>  $groups
     */
    private function flattenGroup(string $path, array $rows, array &$values, array &$groups): void
    {
        $rows = array_values(array_filter($rows, 'is_array'));
        $groups[$path] = $rows;

        $columns = [];
        $nested = [];
        foreach ($rows as $i => $row) {
            $rowPath = $path.self::INDEX_SEPARATOR.($i + 1);
            foreach ($row as $child => $value) {
                if (is_array($value)) {
                    $this->flattenGroup($rowPath.'.'.$child, $value, $values, $groups);
                    $nested[$child] = array_merge($nested[$child] ?? [], $value);

                    continue;
                }
                $values[$rowPath.'.'.$child] = (string) $value;
                $columns[$child][] = (string) $value;
            }
        }

        foreach ($columns as $child => $list) {
            $values[$path.'.'.$child] = $list;
        }

        // Across-all-rows view of nested groups (e.g. every officer of every
        // listed organization in one table).
        foreach ($nested as $child => $allRows) {
            if (! isset($groups[$path.'.'.$child])) {
                $this->flattenAcross($path.'.'.$child, $allRows, $values);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $values
     */
    private function flattenAcross(string $path, array $rows, array &$values): void
    {
        $columns = [];
        $nested = [];
        foreach ($rows as $row) {
            foreach ((array) $row as $child => $value) {
                if (is_array($value)) {
                    $nested[$child] = array_merge($nested[$child] ?? [], $value);
                } else {
                    $columns[$child][] = (string) $value;
                }
            }
        }
        foreach ($columns as $child => $list) {
            $values[$path.'.'.$child] = $list;
        }
        foreach ($nested as $child => $allRows) {
            $this->flattenAcross($path.'.'.$child, $allRows, $values);
        }
    }

    /**
     * Expand every `{{#P}} … {{/P}}` block in a WordprocessingML part.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $groups
     */
    public function expandBlocks(string $xml, array $groups): string
    {
        if (! str_contains($xml, '{{#')) {
            return $xml;
        }

        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = true;
        if (! @$dom->loadXML($xml)) {
            return $xml;
        }
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::W_NS);

        // Bounded: each pass expands (or discards) one block.
        for ($guard = 0; $guard < 5000; $guard++) {
            $open = $this->firstOpenMarker($xpath);
            if ($open === null) {
                break;
            }
            [$textNode, $path] = $open;
            $this->expandOne($xpath, $textNode, $path, $groups);
        }

        return $dom->saveXML();
    }

    /**
     * @return array{0: \DOMElement, 1: string}|null
     */
    private function firstOpenMarker(\DOMXPath $xpath): ?array
    {
        $nodes = $xpath->query('//w:t[contains(., "{{#")]');
        if ($nodes === false) {
            return null;
        }
        foreach ($nodes as $node) {
            if (preg_match('/\{\{#([A-Za-z0-9_.]+)\}\}/', $node->textContent, $m)) {
                return [$node, $m[1]];
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $groups
     */
    private function expandOne(\DOMXPath $xpath, \DOMElement $openText, string $path, array $groups): void
    {
        $openMarker = '{{#'.$path.'}}';
        $closeMarker = '{{/'.$path.'}}';
        $openParagraph = $this->ancestorParagraph($openText);
        $rows = $groups[$path] ?? [];

        if ($openParagraph === null) {
            $this->replaceText($openText, $openMarker, '');

            return;
        }

        // Inline block: both markers in one paragraph → repeat that paragraph.
        if (str_contains($this->paragraphText($openParagraph), $closeMarker)) {
            $this->removeMarker($xpath, $openParagraph, $openMarker);
            $this->removeMarker($xpath, $openParagraph, $closeMarker);
            $this->cloneBlock([$openParagraph], $openParagraph, $path, $rows, $xpath);
            $openParagraph->parentNode?->removeChild($openParagraph);

            return;
        }

        // Find the closing paragraph among the following siblings.
        $block = [];
        $closeParagraph = null;
        for ($node = $openParagraph->nextSibling; $node !== null; $node = $node->nextSibling) {
            if ($node instanceof \DOMElement && str_contains($node->textContent, $closeMarker)) {
                $closeParagraph = $node->localName === 'p' ? $node : null;
                if ($closeParagraph === null) {
                    // The close marker is nested inside a sibling (e.g. a table):
                    // not a well-formed block at this level.
                    break;
                }

                break;
            }
            $block[] = $node;
        }

        if ($closeParagraph === null) {
            // Unmatched: drop the stray marker so it never prints.
            $this->removeMarker($xpath, $openParagraph, $openMarker);
            $this->dropIfEmpty($openParagraph);

            return;
        }

        // Content sharing a marker paragraph stays outside the block.
        $this->removeMarker($xpath, $openParagraph, $openMarker);
        $this->removeMarker($xpath, $closeParagraph, $closeMarker);

        $this->cloneBlock($block, $closeParagraph, $path, $rows, $xpath);

        foreach ($block as $node) {
            $node->parentNode?->removeChild($node);
        }
        $this->dropIfEmpty($openParagraph);
        $this->dropIfEmpty($closeParagraph);
    }

    /**
     * Insert one re-keyed copy of $block per row before $before.
     *
     * @param  array<int, \DOMNode>  $block
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function cloneBlock(array $block, \DOMNode $before, string $path, array $rows, \DOMXPath $xpath): void
    {
        $parent = $before->parentNode;
        if ($parent === null) {
            return;
        }

        foreach (array_keys($rows) as $i) {
            $rowPath = $path.self::INDEX_SEPARATOR.($i + 1);
            foreach ($block as $node) {
                $clone = $node->cloneNode(true);
                $this->rekey($clone, $path, $rowPath, $xpath);
                $parent->insertBefore($clone, $before);
            }
        }
    }

    private function rekey(\DOMNode $node, string $path, string $rowPath, \DOMXPath $xpath): void
    {
        $texts = $node instanceof \DOMElement && $node->localName === 't'
            ? [$node]
            : iterator_to_array($xpath->query('.//w:t', $node) ?: []);

        foreach ($texts as $text) {
            $value = $text->textContent;
            if (! str_contains($value, '{{')) {
                continue;
            }
            $rekeyed = str_replace(
                ['{{'.$path.'.', '{{#'.$path.'.', '{{/'.$path.'.'],
                ['{{'.$rowPath.'.', '{{#'.$rowPath.'.', '{{/'.$rowPath.'.'],
                $value,
            );
            if ($rekeyed !== $value) {
                $text->textContent = $rekeyed;
            }
        }
    }

    private function removeMarker(\DOMXPath $xpath, \DOMElement $paragraph, string $marker): void
    {
        foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $text) {
            if (str_contains($text->textContent, $marker)) {
                $this->replaceText($text, $marker, '');
            }
        }
    }

    private function replaceText(\DOMElement $text, string $search, string $replace): void
    {
        $text->textContent = str_replace($search, $replace, $text->textContent);
    }

    private function dropIfEmpty(\DOMElement $paragraph): void
    {
        $hasContent = trim($paragraph->textContent) !== ''
            || $paragraph->getElementsByTagNameNS(self::W_NS, 'drawing')->length > 0;
        if (! $hasContent) {
            $paragraph->parentNode?->removeChild($paragraph);
        }
    }

    private function ancestorParagraph(\DOMNode $node): ?\DOMElement
    {
        for ($current = $node; $current !== null; $current = $current->parentNode) {
            if ($current instanceof \DOMElement && $current->localName === 'p' && $current->namespaceURI === self::W_NS) {
                return $current;
            }
        }

        return null;
    }

    private function paragraphText(\DOMElement $paragraph): string
    {
        return $paragraph->textContent;
    }

    /**
     * `{{profile.*}}` from the generating user (org keys from the picked
     * organization) and `{{system.*}}` from the clock/school calendar.
     *
     * @return array{values: array<string, mixed>, images: array<string, mixed>}
     */
    private function universal(?User $user, ?Organization $organization, string $disk): array
    {
        $data = app(DocxTemplateData::class)->build(
            [],
            collect(),
            $user?->profile()->first(),
            $organization,
            $disk,
        );

        foreach (UniversalField::keysBySource('system') as $key) {
            $data['values']['system.'.$key] = match ($key) {
                'current_date' => Carbon::now()->format('F j, Y'),
                'current_datetime' => Carbon::now()->format('F j, Y g:i A'),
                'current_time' => Carbon::now()->format('g:i A'),
                default => (string) (UniversalField::systemValue($key) ?? ''),
            };
        }

        return $data;
    }
}
