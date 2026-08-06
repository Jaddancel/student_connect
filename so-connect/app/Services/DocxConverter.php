<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Converts documents between office formats (docx→pdf, html→docx, docx→html).
 *
 * Prefers the `docxconvert` sidecar, which keeps a LibreOffice listener warm
 * via unoserver — a cold `soffice` start burns several seconds and most of a
 * core on every call. When the sidecar is unconfigured or unreachable this
 * falls back to the `soffice` binary baked into the app image, so conversion
 * still works in artisan/CI contexts where no sidecar is running.
 *
 * Conversion is best-effort: callers get null rather than an exception so they
 * can choose their own fallback (see {@see DocxTemplateService}).
 */
class DocxConverter
{
    /**
     * Convert a file to `$toFormat`, writing the result into `$outDir`.
     *
     * @return string|null absolute path to the converted file, or null on failure
     */
    public function convert(string $inputPath, string $toFormat, string $outDir): ?string
    {
        if (! is_file($inputPath)) {
            return null;
        }

        File::ensureDirectoryExists($outDir);

        $outputPath = rtrim($outDir, '/').'/'.pathinfo($inputPath, PATHINFO_FILENAME).'.'.$toFormat;

        if ($this->convertViaSidecar($inputPath, $toFormat, $outputPath)) {
            return $outputPath;
        }

        return $this->convertViaBinary($inputPath, $toFormat, $outDir, $outputPath);
    }

    /**
     * Is a sidecar configured? (Not a reachability check — see {@see healthy()}.)
     */
    public function sidecarConfigured(): bool
    {
        return $this->sidecarUrl() !== '';
    }

    /**
     * Probe the sidecar's health endpoint. Used by diagnostics, not by convert().
     */
    public function healthy(): bool
    {
        $base = $this->sidecarUrl();

        if ($base === '') {
            return false;
        }

        try {
            return Http::timeout(5)->get($base.'/health')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * POST the document to the warm-listener sidecar and store the response body.
     */
    private function convertViaSidecar(string $inputPath, string $toFormat, string $outputPath): bool
    {
        $base = $this->sidecarUrl();

        if ($base === '') {
            return false;
        }

        $timeout = max((int) config('documents.converter.timeout', 120), 30);

        try {
            $response = Http::timeout($timeout)
                ->attach('file', File::get($inputPath), basename($inputPath))
                ->post($base.'/convert', ['to' => $toFormat]);

            if (! $response->successful()) {
                Log::warning('docxconvert sidecar returned non-200; falling back to local soffice.', [
                    'status' => $response->status(),
                    'to' => $toFormat,
                ]);

                return false;
            }

            $body = $response->body();

            if ($body === '') {
                return false;
            }

            File::put($outputPath, $body);

            return is_file($outputPath);
        } catch (\Throwable $throwable) {
            Log::warning('docxconvert sidecar unreachable; falling back to local soffice.', [
                'error' => $throwable->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Run the locally installed LibreOffice headless binary.
     */
    private function convertViaBinary(string $inputPath, string $toFormat, string $outDir, string $outputPath): ?string
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
        } catch (\Throwable) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        return is_file($outputPath) ? $outputPath : null;
    }

    private function sidecarUrl(): string
    {
        return rtrim((string) config('documents.converter.url', ''), '/');
    }
}
