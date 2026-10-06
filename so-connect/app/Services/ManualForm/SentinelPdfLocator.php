<?php

namespace App\Services\ManualForm;

use App\Models\Template as FormTemplate;
use App\Services\DocxTemplateService;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Locates each field's true position on the rendered page by replacing every
 * placeholder with a unique text marker, rendering the template to PDF, and
 * reading each marker's bounding box with Poppler's `pdftotext -bbox`.
 *
 * This is the authoritative position source: unlike estimating a cell's
 * coordinates from OOXML, it reflects exactly where Word/LibreOffice laid the
 * field out (fonts, spacing, images and all), which is what a signature crop
 * needs to land on the right spot rather than a neighbouring cell.
 *
 * Best-effort: any failure (no converter, `pdftotext` missing, a marker that
 * did not render) yields `ok:false` / a missing key, and the caller falls back
 * to the coarser OOXML/vision path.
 */
class SentinelPdfLocator
{
    /** Longest edge (points→normalized) guard; letters+digits stay one word. */
    private const TOKEN_PREFIX = 'MFX';

    private const TOKEN_SUFFIX = 'XFM';

    public function __construct(private readonly DocxTemplateService $docx) {}

    /**
     * @param  array<int,array{key:string}>  $fields
     * @return array{
     *     ok: bool,
     *     anchors: array<string,array{page:int,bounds:array<int,float>}>,
     *     pages: array<int,array{words:array<int,array{x1:float,y1:float,x2:float,y2:float}>}>
     * }
     */
    public function locate(FormTemplate $template, array $fields): array
    {
        $empty = ['ok' => false, 'anchors' => [], 'pages' => []];

        $tokenToKey = [];
        $values = [];
        $i = 0;
        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $token = $this->token($i++);
            $tokenToKey[$token] = $key;
            $values[$key] = $token;
        }
        if ($tokenToKey === []) {
            return $empty;
        }

        try {
            $pdfPath = $this->docx->toPdf($this->docx->populate($template, $values));
        } catch (\Throwable $e) {
            Log::info('Sentinel locator: render unavailable: '.$e->getMessage());

            return $empty;
        }

        try {
            $xhtml = $this->runPdfToText($pdfPath);
            if ($xhtml === null) {
                return $empty;
            }

            $pages = $this->parseWords($xhtml);
            if ($pages === []) {
                return $empty;
            }

            return [
                'ok' => true,
                'anchors' => $this->anchorsFrom($pages, $tokenToKey),
                'pages' => array_map(fn ($p) => ['words' => $p['words']], $pages),
            ];
        } finally {
            @File::delete($pdfPath);
        }
    }

    /**
     * The marker for field ordinal `$i` — letters and digits only so Poppler
     * keeps it as a single `<word>`, and rare enough not to collide with copy.
     */
    public function token(int $i): string
    {
        return self::TOKEN_PREFIX.str_pad((string) $i, 4, '0', STR_PAD_LEFT).self::TOKEN_SUFFIX;
    }

    /**
     * The crop region for a signature: the accurate marker anchor, widened to
     * the cell column and extended down to just above the next line of text or
     * field below it (the printed name/caption), so the blank signing space is
     * captured without the surrounding print. Falls back to a fixed band when
     * nothing sits below.
     *
     * @param  array{page:int,bounds:array<int,float>}  $anchor
     * @param  array<int,array{x1:float,y1:float,x2:float,y2:float}>  $pageWords
     * @param  array{0:float,1:float}|null  $columnRange
     * @return array<int,float>  [x1,y1,x2,y2]
     */
    public function signatureRegion(array $anchor, array $pageWords, ?array $columnRange, float $fallbackBand): array
    {
        [$ax1, $ay1, $ax2, $ay2] = $anchor['bounds'];
        $pad = 0.004;

        $x1 = $columnRange[0] ?? max(0.0, $ax1 - 0.01);
        $x2 = $columnRange[1] ?? min(1.0, $ax2 + 0.01);
        if ($x2 <= $x1) {
            $x1 = $ax1;
            $x2 = $ax2;
        }

        $belowY = null;
        foreach ($pageWords as $word) {
            if ($word['y1'] <= $ay2 + $pad) {
                continue; // not below the anchor
            }
            $centre = ($word['x1'] + $word['x2']) / 2;
            if ($centre < $x1 || $centre > $x2) {
                continue; // not in this column
            }
            $belowY = $belowY === null ? $word['y1'] : min($belowY, $word['y1']);
        }

        $y1 = max(0.0, $ay1 - $pad);
        $y2 = $belowY !== null && $belowY - $pad > $y1
            ? $belowY - $pad
            : min(1.0, $ay2 + $fallbackBand);

        return [
            $this->clamp01($x1),
            $this->clamp01($y1),
            $this->clamp01($x2),
            $this->clamp01($y2),
        ];
    }

    /**
     * Parse `pdftotext -bbox` XHTML into normalized words per 0-indexed page.
     *
     * @return array<int,array{width:float,height:float,words:array<int,array{x1:float,y1:float,x2:float,y2:float,text:string}>}>
     */
    public function parseWords(string $xhtml): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // -bbox output is XHTML; load as XML so the camelCase xMin/yMin
        // attributes survive (loadHTML would lowercase them).
        $loaded = $dom->loadXML($xhtml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return [];
        }

        $pages = [];
        $pageIndex = 0;
        foreach ($dom->getElementsByTagName('page') as $page) {
            if (! $page instanceof DOMElement) {
                continue;
            }
            $width = (float) $page->getAttribute('width');
            $height = (float) $page->getAttribute('height');
            if ($width <= 0 || $height <= 0) {
                $pageIndex++;

                continue;
            }

            $words = [];
            foreach ($page->getElementsByTagName('word') as $word) {
                if (! $word instanceof DOMElement) {
                    continue;
                }
                $x1 = (float) $word->getAttribute('xMin');
                $y1 = (float) $word->getAttribute('yMin');
                $x2 = (float) $word->getAttribute('xMax');
                $y2 = (float) $word->getAttribute('yMax');
                $text = trim($word->textContent);
                if ($text === '' || $x2 <= $x1 || $y2 <= $y1) {
                    continue;
                }
                $words[] = [
                    'x1' => $this->clamp01($x1 / $width),
                    'y1' => $this->clamp01($y1 / $height),
                    'x2' => $this->clamp01($x2 / $width),
                    'y2' => $this->clamp01($y2 / $height),
                    'text' => $text,
                ];
            }

            $pages[$pageIndex] = ['width' => $width, 'height' => $height, 'words' => $words];
            $pageIndex++;
        }

        return $pages;
    }

    /**
     * @param  array<int,array{words:array<int,array{x1:float,y1:float,x2:float,y2:float,text:string}>}>  $pages
     * @param  array<string,string>  $tokenToKey
     * @return array<string,array{page:int,bounds:array<int,float>}>
     */
    private function anchorsFrom(array $pages, array $tokenToKey): array
    {
        $anchors = [];
        foreach ($pages as $index => $page) {
            foreach ($page['words'] as $word) {
                $key = $tokenToKey[$word['text']] ?? null;
                if ($key === null || isset($anchors[$key])) {
                    continue;
                }
                $anchors[$key] = [
                    'page' => (int) $index,
                    'bounds' => [$word['x1'], $word['y1'], $word['x2'], $word['y2']],
                ];
            }
        }

        return $anchors;
    }

    private function runPdfToText(string $pdfPath): ?string
    {
        try {
            $process = new Process(['pdftotext', '-bbox', $pdfPath, '-']);
            $process->setTimeout(60);
            $process->run();
            if (! $process->isSuccessful()) {
                Log::info('Sentinel locator: pdftotext failed: '.$process->getErrorOutput());

                return null;
            }
            $out = $process->getOutput();

            return $out !== '' ? $out : null;
        } catch (ProcessFailedException|\Throwable $e) {
            Log::info('Sentinel locator: pdftotext unavailable: '.$e->getMessage());

            return null;
        }
    }

    private function clamp01(float $value): float
    {
        return round(max(0.0, min(1.0, $value)), 4);
    }
}
