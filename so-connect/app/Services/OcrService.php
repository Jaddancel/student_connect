<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client for the PaddleOCR FastAPI sidecar.
 *
 * The sidecar exposes:
 *   POST /extract  (multipart "file") -> {"blocks": [{"text", "bbox", "confidence"}, ...]}
 *   GET  /health                      -> {"status": "ok"}
 */
class OcrService
{
    public function __construct(
        private readonly string $serviceUrl,
        private readonly int $timeout,
    ) {
    }

    /**
     * Send an image to the OCR service and return the raw text blocks.
     *
     * @return array<int, array{text: string, bbox: array, confidence: float}>
     */
    public function extract(string $imagePath): array
    {
        if (! is_file($imagePath)) {
            throw new RuntimeException('OCR source image does not exist: '.$imagePath);
        }

        $response = $this->client()
            ->attach('file', file_get_contents($imagePath), basename($imagePath))
            ->post($this->endpoint('/extract'));

        if (! $response->successful()) {
            throw new RuntimeException(
                'OCR service returned HTTP '.$response->status().': '.$response->body()
            );
        }

        return $response->json('blocks', []);
    }

    /**
     * Returns true when the OCR sidecar reports a healthy status.
     */
    public function health(): bool
    {
        try {
            $response = $this->client()->get($this->endpoint('/health'));

            return $response->successful() && $response->json('status') === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    private function client(): PendingRequest
    {
        return Http::timeout($this->timeout)->acceptJson();
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->serviceUrl, '/').$path;
    }
}
