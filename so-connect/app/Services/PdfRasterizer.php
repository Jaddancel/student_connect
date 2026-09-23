<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Rasterizes a PDF into ordered per-page PNG images using Poppler's
 * `pdfinfo`/`pdftoppm`, invoked through Symfony Process with explicit argument
 * arrays (never a shell string) so filenames cannot be interpolated.
 *
 * The original PDF bytes and their SHA-256 hash are preserved so a session can
 * freeze the exact document that was printed. Rendering is best-effort: on any
 * failure callers receive `ok: false` rather than an exception.
 */
class PdfRasterizer
{
    /**
     * Rasterize `$pdfPath` into `$outDir`, one PNG per page.
     *
     * @return array{
     *     ok: bool,
     *     hash?: string,
     *     bytes?: int,
     *     pages?: array<int,array{index:int,path:string,width:int,height:int}>,
     *     error?: string
     * }
     */
    public function rasterize(string $pdfPath, string $outDir, int $dpi = 150): array
    {
        if (! is_file($pdfPath)) {
            return ['ok' => false, 'error' => 'source PDF not found'];
        }

        $maxPages = max(1, (int) config('services.document_vision.max_pages', 10));
        $dpi = max(72, min($dpi, 300));

        $info = $this->pageCount($pdfPath);
        if ($info === null) {
            return ['ok' => false, 'error' => 'unable to read PDF metadata'];
        }
        if ($info < 1) {
            return ['ok' => false, 'error' => 'PDF has no pages'];
        }
        if ($info > $maxPages) {
            return ['ok' => false, 'error' => "PDF exceeds the {$maxPages}-page limit"];
        }

        File::ensureDirectoryExists($outDir);
        $prefix = rtrim($outDir, '/').'/page';

        $timeout = max(30, (int) config('services.document_vision.rasterize_timeout', 120));

        $process = new Process([
            'pdftoppm',
            '-png',
            '-r', (string) $dpi,
            '-f', '1',
            '-l', (string) $info,
            $pdfPath,
            $prefix,
        ]);
        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('pdftoppm failed to run: '.$e->getMessage());

            return ['ok' => false, 'error' => 'rasterizer unavailable'];
        }

        if (! $process->isSuccessful()) {
            Log::warning('pdftoppm returned non-zero', ['stderr' => $process->getErrorOutput()]);

            return ['ok' => false, 'error' => 'rasterization failed'];
        }

        $pages = $this->collectPages($outDir);
        if ($pages === []) {
            return ['ok' => false, 'error' => 'no pages produced'];
        }

        $bytes = (string) File::get($pdfPath);

        return [
            'ok' => true,
            'hash' => hash('sha256', $bytes),
            'bytes' => strlen($bytes),
            'pages' => $pages,
        ];
    }

    /**
     * Read the page count via `pdfinfo`. Returns null when the tool is missing
     * or the PDF is unreadable.
     */
    public function pageCount(string $pdfPath): ?int
    {
        if (! is_file($pdfPath)) {
            return null;
        }

        $process = new Process(['pdfinfo', $pdfPath]);
        $process->setTimeout(30);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('pdfinfo failed to run: '.$e->getMessage());

            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        if (preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Gather the produced PNGs in page order with their pixel dimensions.
     * `pdftoppm` names files `page-1.png`, `page-2.png`, ... (zero-padded for
     * documents with 10+ pages), so a natural sort restores page order.
     *
     * @return array<int,array{index:int,path:string,width:int,height:int}>
     */
    private function collectPages(string $outDir): array
    {
        $files = glob(rtrim($outDir, '/').'/page-*.png') ?: [];
        natsort($files);

        $pages = [];
        $index = 0;
        foreach ($files as $path) {
            $size = @getimagesize($path);
            $pages[] = [
                'index' => $index,
                'path' => $path,
                'width' => (int) ($size[0] ?? 0),
                'height' => (int) ($size[1] ?? 0),
            ];
            $index++;
        }

        return $pages;
    }
}
