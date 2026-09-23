<?php

namespace App\Services\ManualForm;

use App\Forms\FieldType;
use App\Models\ManualFormSession;
use App\Services\DocumentVision\DocumentVisionClient;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a session's parsing schema from its frozen partial PDF: the fields
 * empty at Start time, bound to freshly measured writable areas. Unlike a plain
 * copy of the template baseline, this re-locates each field against the exact
 * partial document the user will print, so a long digital-form value that grows
 * a table row (and squeezes the fields below it) is reflected here — the common
 * cause of a required field having nowhere left to write by hand.
 *
 * Fields whose baseline resolved to a table cell are re-measured deterministically
 * from that cell's geometry in the populated `.docx`; free-flow fields are
 * re-read by the vision model from the frozen partial pages. If a required
 * extractable field has no writable region, the session is blocked (a value
 * written there could never have been printed, so it can't be read back).
 *
 * See docs/manual-form-parsing-contract.md section 3.
 */
class ManualSessionSchemaGenerator
{
    public function __construct(
        private readonly DocxTableCellLocator $cellLocator,
        private readonly DocumentVisionClient $vision,
    ) {}

    public function generate(string $sessionId): void
    {
        $session = ManualFormSession::find($sessionId);
        if ($session === null || $session->status !== ManualFormSession::STATUS_PREPARING) {
            return;
        }

        $baselineByKey = $this->baselineByKey($session);
        $extractable = (array) $session->extractable_fields;

        $areas = $this->relocateAreas($session, $baselineByKey, $extractable);

        $blocking = [];
        $schemaFields = [];
        foreach ($extractable as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $area = $areas[$key] ?? ($baselineByKey[$key] ?? []);
            $writable = (string) ($area['writable_area'] ?? 'uncertain');

            if (($field['required'] ?? false) && $writable === 'missing') {
                $blocking[] = (string) ($field['label'] ?? $key);
            }

            $schemaFields[] = [
                'key' => $key,
                'label' => $field['label'] ?? $key,
                'type' => $field['type'] ?? FieldType::TEXT,
                'paper_support' => $field['paper_support'] ?? FieldType::PAPER_EXTRACT,
                'options' => $field['options'] ?? null,
                'page' => $area['page'] ?? null,
                'bounds' => $area['bounds'] ?? null,
            ];
        }

        // Signature fields are located from the baseline too so the scan can be
        // checked for presence at review.
        foreach ($baselineByKey as $key => $area) {
            if (($area['paper_support'] ?? null) === FieldType::PAPER_SIGNATURE) {
                $located = $areas[$key] ?? $area;
                $schemaFields[] = [
                    'key' => $key,
                    'label' => $area['label'] ?? $key,
                    'type' => FieldType::SIGNATURE,
                    'paper_support' => FieldType::PAPER_SIGNATURE,
                    'options' => null,
                    'page' => $located['page'] ?? null,
                    'bounds' => $located['bounds'] ?? null,
                ];
            }
        }

        if ($blocking !== []) {
            $session->forceFill([
                'status' => ManualFormSession::STATUS_FAILED,
                'parse_error' => 'These required fields have no space to write on the printed form: '
                    .implode(', ', $blocking).'. Fill them on the digital form instead.',
            ])->save();

            return;
        }

        $session->forceFill([
            'session_schema' => [
                'pages' => $session->page_meta,
                'known_values' => $session->known_fields,
                'extractable_fields' => $schemaFields,
                'digital_only_fields' => $session->digital_only_fields,
            ],
            'status' => ManualFormSession::STATUS_AWAITING_SCAN,
        ])->save();
    }

    /**
     * Re-measure each paper field's writable area against the frozen partial
     * document: table-bound fields from the populated `.docx`'s cell geometry,
     * free-flow fields from the rasterized partial pages via the vision model.
     *
     * @param  array<string,array<string,mixed>>  $baselineByKey
     * @param  array<int,array<string,mixed>>  $extractable
     * @return array<string,array{page:?int,bounds:?array<int,float>,writable_area:string}>
     */
    private function relocateAreas(ManualFormSession $session, array $baselineByKey, array $extractable): array
    {
        $paperKeys = [];
        foreach ($extractable as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $paperKeys[$key] = (string) ($field['type'] ?? FieldType::TEXT);
            }
        }
        foreach ($baselineByKey as $key => $area) {
            if (($area['paper_support'] ?? null) === FieldType::PAPER_SIGNATURE) {
                $paperKeys[(string) $key] = FieldType::SIGNATURE;
            }
        }

        $areas = $this->relocateTableCells($session, $baselineByKey, $paperKeys);

        $freeFlow = [];
        foreach ($paperKeys as $key => $type) {
            if (! isset($areas[$key])) {
                $freeFlow[$key] = $type;
            }
        }

        return $areas + $this->relocateWithVision($session, $baselineByKey, $freeFlow);
    }

    /**
     * @param  array<string,array<string,mixed>>  $baselineByKey
     * @param  array<string,string>  $paperKeys  field key => type
     * @return array<string,array{page:int,bounds:array<int,float>,writable_area:string}>
     */
    private function relocateTableCells(ManualFormSession $session, array $baselineByKey, array $paperKeys): array
    {
        $docxPath = $this->partialDocxPath($session);
        if ($docxPath === null) {
            return [];
        }

        $areas = [];
        foreach ($paperKeys as $key => $type) {
            $cellPath = $baselineByKey[$key]['cell_path'] ?? null;
            if (! is_array($cellPath) || $cellPath === []) {
                continue;
            }

            try {
                $geometry = $this->cellLocator->geometryFor($docxPath, $cellPath, $type);
            } catch (\Throwable) {
                $geometry = null;
            }

            if ($geometry !== null) {
                $areas[$key] = $geometry;
            }
        }

        return $areas;
    }

    /**
     * @param  array<string,array<string,mixed>>  $baselineByKey
     * @param  array<string,string>  $freeFlow  field key => type
     * @return array<string,array{page:int,bounds:?array<int,float>,writable_area:string}>
     */
    private function relocateWithVision(ManualFormSession $session, array $baselineByKey, array $freeFlow): array
    {
        if ($freeFlow === []) {
            return [];
        }

        $pages = $this->frozenPageImages($session);
        if ($pages === []) {
            return [];
        }

        $fields = [];
        foreach ($freeFlow as $key => $type) {
            $fields[] = [
                'key' => $key,
                'label' => (string) ($baselineByKey[$key]['label'] ?? $key),
                'type' => $type,
            ];
        }

        try {
            $result = $this->vision->locateWritableAreas(['fields' => $fields, 'pages' => $pages]);
        } catch (\Throwable) {
            return [];
        }

        return ($result['ok'] ?? false) ? (array) ($result['areas'] ?? []) : [];
    }

    /**
     * @return array<int,array{index:int,image:string}>
     */
    private function frozenPageImages(ManualFormSession $session): array
    {
        $disk = Storage::disk((string) config('documents.disk', 'public'));
        $pages = [];
        foreach ((array) $session->page_meta as $page) {
            $relative = (string) ($page['path'] ?? '');
            if ($relative === '' || ! $disk->exists($relative)) {
                continue;
            }
            $pages[] = [
                'index' => (int) ($page['index'] ?? count($pages)),
                'image' => 'data:image/png;base64,'.base64_encode((string) $disk->get($relative)),
            ];
        }

        return $pages;
    }

    private function partialDocxPath(ManualFormSession $session): ?string
    {
        $disk = (string) config('documents.disk', 'public');
        $relative = 'manual-form/'.$session->getKey().'/partial.docx';
        if (! Storage::disk($disk)->exists($relative)) {
            return null;
        }

        return Storage::disk($disk)->path($relative);
    }

    /**
     * The baseline schema's per-field writable-area data, keyed by field key.
     *
     * @return array<string,array<string,mixed>>
     */
    private function baselineByKey(ManualFormSession $session): array
    {
        $baseline = (array) ($session->baseline_schema['fields'] ?? []);
        $byKey = [];
        foreach ($baseline as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $byKey[$key] = $field;
            }
        }

        return $byKey;
    }
}
