<?php

namespace App\Services\Scanning;

use App\Services\OcrClient;

/**
 * Scans a signed waiver photo. Text zones (name, date, time…) are read by the
 * vision model ({@see VisionZoneReader}) with a PaddleOCR fallback per zone;
 * stamp detection and signature extraction stay with the OCR sidecar, which
 * still has to answer for the scan to count.
 *
 * Only the *type* of each expected value (name/date/time) is passed to the
 * model, never the value itself, so it cannot be nudged into reading what the
 * validator hopes to find.
 */
class WaiverDocumentScanner
{
    public function __construct(
        private readonly OcrClient $ocr,
        private readonly VisionZoneReader $reader,
    ) {}

    /**
     * @param  array<string,mixed>  $template  {reference:{width,height}, zones:[{name,x,y,w,h,type,label?}]}
     * @param  array<string,array{value?:string,type?:string}>  $expected  zone name => expectation
     * @return array<string,mixed> same shape as {@see OcrClient::scanWaiver}, plus `sources`
     */
    public function scan(string $imagePng, array $template, array $expected = []): array
    {
        $zones = [];
        foreach ((array) ($template['zones'] ?? []) as $zone) {
            $name = (string) ($zone['name'] ?? '');
            if ($name === '' || ($zone['type'] ?? 'text') !== 'text') {
                continue;
            }
            $x = (float) ($zone['x'] ?? 0);
            $y = (float) ($zone['y'] ?? 0);
            $type = (string) ($expected[$name]['type'] ?? '');
            $zones[] = [
                'name' => $name,
                'label' => $zone['label'] ?? null,
                'field' => $name,
                'x1' => $x,
                'y1' => $y,
                'x2' => $x + (float) ($zone['w'] ?? 0),
                'y2' => $y + (float) ($zone['h'] ?? 0),
                'kind' => in_array($type, ['name', 'date', 'time'], true) ? $type : null,
            ];
        }
        $zones = array_map(fn (array $z) => array_filter($z, fn ($v) => $v !== null), $zones);

        $scan = $this->ocr->scanWaiver($imagePng, $template);
        if (! ($scan['ok'] ?? false)) {
            return $scan;
        }

        $fields = (array) ($scan['fields'] ?? []);
        $read = $this->reader->read(
            $imagePng,
            (array) ($template['reference'] ?? []),
            $zones,
            'signed event waiver',
            VisionZoneReader::VIEW_CROP,
            array_map('strval', array_filter($fields, 'is_scalar')),
        );

        $sources = [];
        foreach ($zones as $zone) {
            $name = $zone['name'];
            if (isset($read[$name])) {
                $fields[$name] = $read[$name]['value'];
                $sources[$name] = 'vision';
            } else {
                $sources[$name] = 'ocr';
            }
        }

        $scan['fields'] = $fields;
        $scan['sources'] = $sources;

        return $scan;
    }
}
