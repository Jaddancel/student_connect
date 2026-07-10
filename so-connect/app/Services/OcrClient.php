<?php

namespace App\Services;

use App\Models\IdTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the PaddleOCR sidecar (docker/ocr). Sends a student's ID
 * photo plus a template's frozen zone payload and maps the returned per-zone
 * text back onto each zone's destination `field`.
 *
 * Failure is always graceful: a timeout, non-200, or unreachable sidecar yields
 * an empty result (never an exception into the request flow).
 */
class OcrClient
{
    /**
     * @return array{fields: array<string,string>, student_id: ?string, ok: bool, note?: string}
     */
    public function scan(IdTemplate $template, UploadedFile|string $photo, string $side = 'front'): array
    {
        $url = rtrim((string) config('services.ocr.url'), '/').'/scan';
        $timeout = (int) config('services.ocr.timeout', 60);

        try {
            $request = Http::timeout($timeout);

            if ($photo instanceof UploadedFile) {
                $request = $request->attach(
                    'image',
                    file_get_contents($photo->getRealPath()),
                    $photo->getClientOriginalName() ?: 'id.jpg',
                );
            } else {
                $request = $request->attach('image', file_get_contents($photo), basename($photo));
            }

            $response = $request->post($url, [
                'template' => json_encode($template->toScannerPayload($side)),
            ]);

            if (! $response->successful()) {
                Log::warning('OCR sidecar returned non-200', ['status' => $response->status()]);

                return $this->empty('scanner error');
            }

            return $this->mapFields($template, (array) $response->json('fields', []), $side);
        } catch (\Throwable $e) {
            Log::warning('OCR sidecar unreachable: '.$e->getMessage());

            return $this->empty('scanner unavailable');
        }
    }

    /**
     * Map the sidecar's zone-name → text results onto each zone's `field`,
     * exposing `student_id` explicitly.
     *
     * @param  array<string,mixed>  $raw
     * @return array{fields: array<string,string>, student_id: ?string, ok: bool}
     */
    private function mapFields(IdTemplate $template, array $raw, string $side = 'front'): array
    {
        $fields = [];
        foreach ($template->zonesForSide($side) as $zone) {
            $name = $zone['name'] ?? null;
            $field = $zone['field'] ?? $name;
            if (! $name || ! $field) {
                continue;
            }
            if (array_key_exists($name, $raw) && $raw[$name] !== null && $raw[$name] !== '') {
                $fields[$field] = (string) $raw[$name];
            }
        }

        return [
            'fields' => $fields,
            'student_id' => $fields['student_id'] ?? null,
            'ok' => true,
        ];
    }

    /**
     * @return array{fields: array<string,string>, student_id: ?string, ok: bool, note: string}
     */
    private function empty(string $note): array
    {
        return ['fields' => [], 'student_id' => null, 'ok' => false, 'note' => $note];
    }
}
