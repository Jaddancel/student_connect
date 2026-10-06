<?php

namespace App\Services;

use App\Helpers\FormTemplateHelper;
use App\Models\Template as FormTemplate;
use App\Support\DocxChartFiller;
use App\Support\DocxImageLayout;
use App\Support\DocxTemplateProcessor;
use App\Support\UniversalField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Bridges the printed-PDF template between its stored form (rich-text HTML with
 * field-token spans) and Word `.docx` for interchange, and populates uploaded
 * `.docx` form templates with submission data.
 *
 * Tokens cross the boundary as human-editable `{{field_key}}` text placeholders:
 * export turns `<span data-field="k">` into `{{k}}`; import maps `{{k}}`/`${k}`
 * back into token chips. Conversion prefers LibreOffice headless for fidelity and
 * falls back to PhpWord when the binary is unavailable.
 *
 * {@see populate()} is the deterministic counterpart: it merges data into a
 * template uploaded through the template manager, using the same `{{key}}`
 * convention that {@see FormTemplateHelper::extractPlaceholdersFromDocx()}
 * discovers at verify time.
 */
class DocxTemplateService
{
    /** A trailing `#` marks a placeholder whose row/value repeats per data item. */
    private const REPEAT_MARKER = '#';

    public function __construct(private readonly DocxConverter $converter) {}

    /**
     * Merge `$data` into the template's uploaded `.docx` and return the absolute
     * path of the generated file (written to a scratch directory — the caller
     * decides whether to persist it).
     *
     * Placeholders follow the `{{field_key}}` convention; PhpWord's native
     * `${field_key}` is resolved too. A placeholder ending in `#` repeats: if it
     * sits in a table row the row is cloned once per value, otherwise the values
     * are joined into one multi-line run.
     *
     * Charts whose series/category cells hold repeating table tokens are then
     * re-pointed at the table's rows by {@see DocxChartFiller}.
     *
     * Pictures take the size of the table cell their placeholder sits in; a
     * photo set fills its row's cells and adds rows as needed — see
     * {@see DocxImageLayout}.
     *
     * @param  array<string, mixed>  $data  field_key => value (keys are normalized)
     * @param  array<string, string|array<int,string>>  $images  field_key => absolute image path(s)
     * @param  array<string, array<string, string>>  $tables  table field_key => [column key => label]
     */
    public function populate(FormTemplate $template, array $data, array $images = [], array $tables = []): string
    {
        $disk = (string) config('documents.disk', 'public');
        $relativePath = (string) ($template->docx_path ?? '');

        if ($relativePath === '' || ! Storage::disk($disk)->exists($relativePath)) {
            throw new RuntimeException('Template #'.$template->getKey().' has no .docx file on disk.');
        }

        // The subclass fixes the delimiters before PhpWord's constructor repairs
        // placeholders Word split across XML runs — see DocxTemplateProcessor.
        $processor = new DocxTemplateProcessor(Storage::disk($disk)->path($relativePath));
        $values = $this->normalizeData($data);

        $pictures = $this->normalizeData($images);

        try {
            $this->fillRepeating($processor, $values, $pictures);
            $this->fillImages($processor, $pictures);
            $this->fillRemaining($processor, $values);

            // Second pass for templates authored against PhpWord's own syntax.
            $processor->useMacroChars(
                DocxTemplateProcessor::LEGACY_OPENING,
                DocxTemplateProcessor::LEGACY_CLOSING,
            );
            $this->fillRepeating($processor, $values, $pictures);
            $this->fillImages($processor, $pictures);
            $this->fillRemaining($processor, $values);
        } finally {
            DocxTemplateProcessor::resetMacroChars();
        }

        $outputPath = $this->tempDir().'/'.$this->outputBasename($template).'.docx';
        $processor->saveAs($outputPath);

        if (! is_file($outputPath)) {
            throw new RuntimeException('Failed to write the populated .docx file.');
        }

        (new DocxChartFiller)->fill($outputPath, $values, $tables);

        return $outputPath;
    }

    /**
     * Convert a generated `.docx` to PDF; returns the absolute path of the PDF.
     */
    public function toPdf(string $docxPath): string
    {
        $pdfPath = $this->converter->convert($docxPath, 'pdf', dirname($docxPath));

        if ($pdfPath === null) {
            throw new RuntimeException('Could not convert the document to PDF — no converter is available.');
        }

        return $pdfPath;
    }

    /**
     * Convert template HTML to a .docx file; returns the absolute temp path.
     * Field tokens are first flattened to `{{field_key}}` placeholders.
     */
    public function htmlToDocx(string $html): string
    {
        $html = $this->tokensToPlaceholders($html);
        $document = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>';

        $workDir = $this->tempDir();
        $htmlPath = $workDir.'/template.html';
        File::put($htmlPath, $document);

        // Primary: LibreOffice headless.
        $docxPath = $this->libreConvert($htmlPath, 'docx', $workDir);
        if ($docxPath !== null) {
            return $docxPath;
        }

        // Fallback: PhpWord. Pin the house default (Times New Roman 12pt) so
        // HTML without an explicit font maps to the authored look rather than
        // PhpWord's own default. Settings are process-wide statics.
        $out = $workDir.'/template.docx';
        \PhpOffice\PhpWord\Settings::setDefaultFontName('Times New Roman');
        \PhpOffice\PhpWord\Settings::setDefaultFontSize(12);
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $html, false, false);
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($out);

        if (! is_file($out)) {
            throw new RuntimeException('Failed to generate the .docx file.');
        }

        return $out;
    }

    /**
     * Convert an uploaded .docx to sanitized-ready HTML and map `{{key}}`/`${key}`
     * placeholders back into field-token spans.
     *
     * @param  array<string,string>  $fields  field_key => field_label for mapping
     */
    public function docxToHtml(UploadedFile $file, array $fields = []): string
    {
        $workDir = $this->tempDir();
        $docxPath = $workDir.'/upload.docx';
        File::put($docxPath, File::get($file->getRealPath()));

        $html = null;

        // Primary: LibreOffice headless.
        $htmlPath = $this->libreConvert($docxPath, 'html', $workDir);
        if ($htmlPath !== null && is_file($htmlPath)) {
            $html = File::get($htmlPath);
        }

        // Fallback: PhpWord HTML writer.
        if ($html === null) {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($docxPath);
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
            ob_start();
            $writer->save('php://output');
            $html = (string) ob_get_clean();
        }

        $html = $this->extractBody($html);

        return $this->placeholdersToTokens($html, $fields);
    }

    /**
     * Replace each field-token span with a literal `{{field_key}}` placeholder.
     */
    private function tokensToPlaceholders(string $html): string
    {
        // Universal tokens use a namespaced `{{profile.key}}` placeholder so they
        // stay distinguishable from ordinary field tokens on re-import.
        $html = (string) preg_replace_callback(
            '/<span\b[^>]*\bdata-universal="([^"]+)"[^>]*>.*?<\/span>/is',
            fn ($m) => '{{profile.'.$m[1].'}}',
            $html,
        );

        return (string) preg_replace_callback(
            '/<span\b[^>]*\bdata-field="([^"]+)"[^>]*>.*?<\/span>/is',
            fn ($m) => '{{'.$m[1].'}}',
            $html,
        );
    }

    /**
     * Replace `{{key}}` / `${key}` placeholders with field-token spans, resolving
     * labels from the current fields. Unknown keys become "unmapped" chips.
     *
     * @param  array<string,string>  $fields
     */
    private function placeholdersToTokens(string $html, array $fields): string
    {
        // Namespaced `{{profile.key}}` placeholders map back to universal tokens,
        // resolving their label from the registry. Unknown keys fall through to
        // the ordinary field pass below.
        $html = (string) preg_replace_callback(
            '/\{\{\s*profile\.([A-Za-z0-9_]+)\s*\}\}|\$\{\s*profile\.([A-Za-z0-9_]+)\s*\}/',
            function ($m) {
                $key = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
                if ($key === '' || ! UniversalField::has($key)) {
                    return $m[0];
                }

                return '<span class="field-token" data-universal="'.e($key).'" contenteditable="false">'.e(UniversalField::label($key)).'</span>';
            },
            $html,
        );

        return (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}|\$\{\s*([A-Za-z0-9_]+)\s*\}/',
            function ($m) use ($fields) {
                $key = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
                if ($key === '') {
                    return $m[0];
                }
                $known = array_key_exists($key, $fields);
                $label = $known ? $fields[$key] : $key;
                $class = $known ? 'field-token' : 'field-token field-token--unmapped';

                return '<span class="'.$class.'" data-field="'.e($key).'" contenteditable="false">'.e($label).'</span>';
            },
            $html,
        );
    }

    /**
     * Run LibreOffice conversion (warm sidecar, else the local binary); returns
     * the output path or null when no converter could produce a file.
     */
    private function libreConvert(string $inputPath, string $toFormat, string $outDir): ?string
    {
        return $this->converter->convert($inputPath, $toFormat, $outDir);
    }

    /**
     * Clone/repeat every `{{key#}}` placeholder, then resolve the indexed
     * placeholders that cloning leaves behind.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $images
     */
    private function fillRepeating(TemplateProcessor $processor, array $values, array $images = []): void
    {
        foreach ($processor->getVariables() as $variable) {
            if (! str_ends_with($variable, self::REPEAT_MARKER)) {
                continue;
            }

            // Cloning a row consumes every placeholder in it, so a repeating
            // sibling column may already be gone by the time we reach it.
            if (! in_array($variable, $processor->getVariables(), true)) {
                continue;
            }

            $key = $this->keyFor($variable);
            $hasTextRows = array_key_exists($key, $values);
            $rows = $this->listValues($values[$key] ?? null);

            // Pictures are laid out by fillImages() against the cell the token
            // sits in (a photo set fills the row's cells, adding rows as needed).
            if (! $hasTextRows && array_key_exists($key, $images)) {
                continue;
            }

            try {
                $processor->cloneRow($variable, max(count($rows), 1));
            } catch (\Throwable) {
                // Not inside a table — emit text values as one multi-line run.
                if ($hasTextRows) {
                    $processor->setValue($variable, $this->xmlValue(implode("\n", $rows)));
                }
            }
        }

        $this->fillIndexed($processor, $values);
    }

    /**
     * Resolve the `key##N` / `key#N` placeholders produced by cloneRow.
     *
     * @param  array<string, mixed>  $values
     */
    private function fillIndexed(TemplateProcessor $processor, array $values): void
    {
        foreach ($processor->getVariables() as $variable) {
            // `key##N` — a repeating placeholder that cloneRow indexed.
            // `key#N`  — a plain placeholder that happened to sit in a cloned row.
            if (preg_match('/^(.+)##(\d+)$/', $variable, $matches)) {
                $isRepeating = true;
            } elseif (preg_match('/^(.+)#(\d+)$/', $variable, $matches)) {
                $isRepeating = false;
            } else {
                continue;
            }

            $value = $values[$this->keyFor($matches[1])] ?? null;
            if (! array_key_exists($this->keyFor($matches[1]), $values)) {
                continue;
            }
            $index = (int) $matches[2] - 1;

            if ($isRepeating || is_array($value)) {
                $resolved = $this->listValues($value)[$index] ?? '';
            } else {
                // A non-repeating field repeats its single value down the rows.
                $resolved = $this->scalarize($value);
            }

            $processor->setValue($variable, $this->xmlValue($resolved));
        }
    }

    /**
     * Swap picture placeholders for the actual images, each sized to the
     * table cell its token sits in (see {@see DocxImageLayout}). Runs before
     * {@see fillRemaining()}, which would otherwise blank them as text.
     *
     * A single image (string path) fills its cell; a photo set (list of
     * paths) fills the cells of its row left to right, cloning the row when
     * the columns run out. Outside a table, pictures keep a bounded default
     * size and a photo set prints its images side by side.
     *
     * @param  array<string, string|array<int,string>>  $images  normalized key => absolute path(s)
     */
    private function fillImages(TemplateProcessor $processor, array $images): void
    {
        if ($images === [] || ! $processor instanceof DocxTemplateProcessor) {
            return;
        }

        $jobs = [];
        foreach (array_unique($processor->getVariables()) as $variable) {
            $key = $this->keyFor($variable);
            if (! array_key_exists($key, $images)) {
                continue;
            }

            $paths = $this->imagePaths($images[$key]);
            if ($paths === []) {
                continue;
            }

            // `key#N` / `key##N`: the token sat in a row another repeating
            // column cloned — a photo set gives row N its Nth picture, a single
            // image repeats down the rows like a scalar text value does.
            $index = $this->imageIndexForVariable($variable);
            if ($index !== null) {
                $path = is_array($images[$key]) ? ($paths[$index] ?? null) : $paths[0];
                if ($path !== null) {
                    $jobs[$variable] = ['paths' => [$path], 'set' => false];
                }

                continue;
            }

            $jobs[$variable] = ['paths' => $paths, 'set' => is_array($images[$key])];
        }

        if ($jobs === []) {
            return;
        }

        $layout = new DocxImageLayout($processor->packagePart('word/styles.xml'));
        $placements = [];
        $processor->transformParts(function (string $xml) use ($layout, $jobs, $processor, &$placements) {
            [$xml, $placed] = $layout->place($xml, $jobs, fn (string $name) => $processor->macro($name));
            $placements += $placed;

            return $xml;
        });

        foreach ($placements as $name => $placement) {
            $processor->setImageValue($name, DocxImageLayout::imageValue($placement['path'], $placement['box']));
        }
    }

    /**
     * Set every placeholder still standing, blanking those with no data so the
     * generated document never ships raw `{{key}}` text.
     *
     * @param  array<string, mixed>  $values
     */
    private function fillRemaining(TemplateProcessor $processor, array $values): void
    {
        foreach ($processor->getVariables() as $variable) {
            $processor->setValue(
                $variable,
                $this->xmlValue($this->scalarize($values[$this->keyFor($variable)] ?? null)),
            );
        }
    }

    /**
     * Re-key caller data so lookups match however the placeholder was written.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[FormTemplateHelper::normalizeFieldKey((string) $key)] = $value;
        }

        return $normalized;
    }

    /**
     * The data key a placeholder resolves against (repeat marker stripped).
     */
    private function keyFor(string $variable): string
    {
        if (preg_match('/^(.+)##\d+$/', $variable, $matches)
            || preg_match('/^(.+)#\d+$/', $variable, $matches)) {
            $variable = $matches[1];
        }

        return FormTemplateHelper::normalizeFieldKey(rtrim($variable, self::REPEAT_MARKER));
    }

    /**
     * @return array<int,string>
     */
    private function imagePaths(mixed $value): array
    {
        return array_values(array_filter(
            array_map('strval', is_array($value) ? $value : [$value]),
            fn (string $path) => $path !== '' && is_file($path),
        ));
    }

    /**
     * Zero-based image index from cloneRow-produced placeholders.
     */
    private function imageIndexForVariable(string $variable): ?int
    {
        if (preg_match('/^.+##(\d+)$/', $variable, $matches)
            || preg_match('/^.+#(\d+)$/', $variable, $matches)) {
            return max(0, (int) $matches[1] - 1);
        }

        return null;
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
            fn ($item) => $this->scalarize($item),
            is_array($value) ? $value : [$value],
        ));
    }

    /**
     * Flatten a field value to the text that belongs in the document.
     */
    private function scalarize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('F j, Y');
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => $this->scalarize($item), $value));
        }

        return (string) $value;
    }

    /**
     * Escape for WordprocessingML. Newlines are left intact — PhpWord turns them
     * into `<w:br/>` when the value is applied.
     */
    private function xmlValue(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function outputBasename(FormTemplate $template): string
    {
        $slug = Str::slug((string) ($template->template_name ?? 'template'));

        return ($slug !== '' ? $slug : 'template').'-v'.(int) ($template->version ?? 1);
    }

    /**
     * Pull the inner-body markup out of a full HTML document, if present.
     */
    private function extractBody(string $html): string
    {
        if (preg_match('/<body\b[^>]*>(.*)<\/body>/is', $html, $m)) {
            return $m[1];
        }

        return $html;
    }

    private function tempDir(): string
    {
        $dir = storage_path('app/tmp/docx-'.Str::random(12));
        File::ensureDirectoryExists($dir);

        return $dir;
    }
}
