<?php

namespace App\Services;

use App\Support\UniversalField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Bridges the printed-PDF template between its stored form (rich-text HTML with
 * field-token spans) and Word `.docx` for interchange.
 *
 * Tokens cross the boundary as human-editable `{{field_key}}` text placeholders:
 * export turns `<span data-field="k">` into `{{k}}`; import maps `{{k}}`/`${k}`
 * back into token chips. Conversion prefers LibreOffice headless for fidelity and
 * falls back to PhpWord when the binary is unavailable.
 */
class DocxTemplateService
{
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

        // Fallback: PhpWord.
        $out = $workDir.'/template.docx';
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
     * Run LibreOffice headless conversion; returns the output path or null if the
     * binary is unavailable / the conversion failed.
     */
    private function libreConvert(string $inputPath, string $toFormat, string $outDir): ?string
    {
        $binary = (string) config('documents.libreoffice.binary', 'soffice');
        $timeout = max((int) config('documents.libreoffice.timeout', 120), 30);

        $process = new Process([
            $binary, '--headless', '--convert-to', $toFormat,
            '--outdir', $outDir, $inputPath,
        ]);
        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $expected = $outDir.'/'.pathinfo($inputPath, PATHINFO_FILENAME).'.'.$toFormat;

        return is_file($expected) ? $expected : null;
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
