<?php

namespace App\Services\Scanning;

use App\Models\IdTemplate;
use App\Services\OcrClient;
use Illuminate\Http\UploadedFile;

/**
 * Scans one side of a student ID. The OCR sidecar runs first (PaddleOCR text
 * per zone plus signature-ink crops); each text zone is then read by the
 * vision model ({@see VisionZoneReader}), whose corroborated reading replaces
 * the PaddleOCR text. Zones the model could not read with confidence keep the
 * PaddleOCR text. Name splitting and date normalization run on the merged
 * values exactly as before ({@see OcrClient::mapScan}).
 */
class IdCardScanner
{
    public function __construct(
        private readonly OcrClient $ocr,
        private readonly VisionZoneReader $reader,
    ) {}

    /**
     * @return array{fields: array<string,string>, student_id: ?string, ok: bool, images: array<string,string>, note?: ?string, sources: array<string,string>}
     */
    public function scan(IdTemplate $template, UploadedFile|string $photo, string $side = 'front'): array
    {
        $payload = $template->toScannerPayload($side);
        $textZones = array_values(array_filter(
            (array) ($payload['zones'] ?? []),
            fn (array $zone) => ($zone['type'] ?? 'text') === 'text' && ($zone['name'] ?? '') !== '',
        ));

        $ocr = $this->ocr->scanZones($template, $photo, $side);

        $bytes = (string) @file_get_contents($photo instanceof UploadedFile ? $photo->getRealPath() : $photo);
        $read = $this->reader->read(
            $bytes,
            (array) ($payload['reference'] ?? []),
            $textZones,
            'Philippine student ID card',
            VisionZoneReader::VIEW_PAGE,
            $ocr['fields'],
        );

        if (! $ocr['ok'] && $read === []) {
            return ['fields' => [], 'images' => [], 'student_id' => null, 'ok' => false, 'note' => $ocr['note'] ?? 'scanner unavailable', 'sources' => []];
        }

        $texts = $ocr['fields'];
        $sources = [];
        foreach ($textZones as $zone) {
            $name = (string) $zone['name'];
            $field = (string) ($zone['field'] ?: $name);
            if (isset($read[$name])) {
                $texts[$name] = $read[$name]['value'];
                $sources[$field] = 'vision';
            } else {
                $sources[$field] = 'ocr';
            }
        }

        $result = $this->ocr->mapScan($template, $texts, $ocr['images'], $side);
        $result['sources'] = $sources;

        return $result;
    }
}
