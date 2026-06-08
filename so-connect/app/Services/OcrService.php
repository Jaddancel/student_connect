<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Thin HTTP client for the PaddleOCR FastAPI sidecar.
 */
class OcrService
{
    public function __construct(
        private readonly string $serviceUrl,
        private readonly int $timeout,
        private readonly HttpFactory $http,
    ) {}

    /**
     * POST /extract — sends the image as multipart and returns the raw blocks.
     *
     * @return array<int, array{text: string, bbox: array<int, array<int, float>>, confidence: float}>
     */
    public function extract(string $imagePath): array
    {
        if (! is_file($imagePath)) {
            throw new RuntimeException("Scan image not found: {$imagePath}");
        }

        $response = $this->http
            ->timeout($this->timeout)
            ->asMultipart()
            ->attach('file', file_get_contents($imagePath), basename($imagePath))
            ->post($this->endpoint('/extract'));

        if (! $response->successful()) {
            throw new RuntimeException(
                "OCR service returned HTTP {$response->status()}: ".$response->body()
            );
        }

        return $response->json('blocks', []);
    }

    /**
     * GET /health — returns true when the sidecar is reachable and ready.
     */
    public function health(): bool
    {
        try {
            $response = $this->http
                ->timeout(min($this->timeout, 10))
                ->get($this->endpoint('/health'));

            return $response->successful() && $response->json('status') === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->serviceUrl, '/').$path;
    }
}
