<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
    public const SOURCE_CACHE_PREFIX = 'onlyoffice:conversion:';

    public function __construct(private readonly OnlyOfficeService $onlyOffice) {}

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

        if (strtolower(pathinfo($inputPath, PATHINFO_EXTENSION)) === 'docx'
            && strtolower($toFormat) === 'pdf'
            && $this->convertViaOnlyOffice($inputPath, $outputPath)) {
            return $outputPath;
        }

        if ($this->convertViaSidecar($inputPath, $toFormat, $outputPath)) {
            return $outputPath;
        }

        return $this->convertViaBinary($inputPath, $toFormat, $outDir, $outputPath);
    }

    /**
     * Render DOCX with the same engine used by the Step 2 editor, preserving
     * layout that LibreOffice can interpret differently (floating shapes,
     * anchored lines, and font metrics in particular).
     */
    private function convertViaOnlyOffice(string $inputPath, string $outputPath): bool
    {
        if (! $this->onlyOffice->enabled()
            || $this->onlyOffice->internalUrl() === ''
            || $this->onlyOffice->appUrl() === '') {
            return false;
        }

        $conversionId = Str::lower(Str::random(32));
        $cacheKey = self::SOURCE_CACHE_PREFIX.$conversionId;

        try {
            Cache::store('file')->put($cacheKey, File::get($inputPath), now()->addMinutes(5));

            $sourceClaims = [
                'cid' => $conversionId,
                'purpose' => 'conversion-source',
                'exp' => now()->addMinutes(5)->getTimestamp(),
            ];
            $sourceUrl = $this->onlyOffice->appUrl().'/onlyoffice/conversion/'.$conversionId.'/source?token='
                .rawurlencode($this->onlyOffice->sign($sourceClaims));
            $key = 'conv-'.$conversionId;
            $payload = [
                'async' => false,
                'filetype' => 'docx',
                'key' => $key,
                'outputtype' => 'pdf',
                'title' => basename($inputPath),
                'url' => $sourceUrl,
            ];
            $payload['token'] = $this->onlyOffice->sign($payload);

            $timeout = max((int) config('documents.converter.timeout', 120), 30);
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->post($this->onlyOffice->internalUrl().'/converter?shardkey='.rawurlencode($key), $payload);

            $downloadUrl = $response->successful()
                ? $this->onlyOfficeDownloadUrl((string) $response->json('fileUrl', ''))
                : null;
            if ($downloadUrl === null) {
                return false;
            }

            $converted = Http::timeout($timeout)->get($downloadUrl);
            if (! $converted->successful() || ! str_starts_with($converted->body(), '%PDF-')) {
                return false;
            }

            File::put($outputPath, $converted->body());

            return is_file($outputPath);
        } catch (\Throwable $throwable) {
            Log::warning('OnlyOffice conversion failed; falling back to LibreOffice.', [
                'error' => $throwable->getMessage(),
            ]);

            return false;
        } finally {
            Cache::store('file')->forget($cacheKey);
        }
    }

    /**
     * Accept conversion downloads only from the configured Document Server.
     * Its response may use the browser-facing origin, so rewrite that one to
     * Laravel's container-reachable internal origin before fetching.
     */
    private function onlyOfficeDownloadUrl(string $url): ?string
    {
        $internal = $this->onlyOffice->internalUrl();
        $public = $this->onlyOffice->publicUrl();

        if ($internal !== '' && ($url === $internal || str_starts_with($url, $internal.'/'))) {
            return $url;
        }

        if ($public !== '' && ($url === $public || str_starts_with($url, $public.'/'))) {
            return $internal.substr($url, strlen($public));
        }

        return null;
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
