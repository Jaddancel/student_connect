<?php

namespace App\Services\ManualForm;

use App\Forms\FieldType;
use App\Models\ManualFormSession;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a session's parsing schema from its frozen partial PDF: the fields
 * empty at Start time, bound to freshly measured writable areas. Unlike a plain
 * copy of the template baseline, table-bound fields are re-measured against the
 * exact partial document the user will print, so a long digital-form value that
 * grows a table row (and squeezes the fields below it) is reflected here — the
 * common cause of a required field having nowhere left to write by hand.
 *
 * Fields whose baseline resolved to a table cell are re-measured deterministically
 * from that cell's geometry in the populated `.docx` (fast, no model). Free-flow
 * fields keep the baseline area the vision model located at save time — the
 * interactive freeze must stay responsive, so no model inference runs here. If a
 * required extractable field has no writable region, the session is blocked (a
 * value written there could never have been printed, so it can't be read back).
 *
 * See docs/manual-form-parsing-contract.md section 3.
 */
class ManualSessionSchemaGenerator
{
    public function __construct(
        private readonly DocxTableCellLocator $cellLocator,
    ) {}

    public function generate(string $sessionId): void
    {
        $session = ManualFormSession::find($sessionId);
        if ($session === null || $session->status !== ManualFormSession::STATUS_PREPARING) {
            return;
        }

        $baselineByKey = $this->baselineByKey($session);
        $extractable = (array) $session->extractable_fields;

        // Re-check only the present/missing verdict against the populated
        // partial; positions stay as the accurate baseline bounds.
        $verdicts = $this->recomputeVerdicts($session, $baselineByKey, $extractable);

        $blocking = [];
        $schemaFields = [];
        foreach ($extractable as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $baseline = $baselineByKey[$key] ?? [];
            $writable = (string) ($verdicts[$key] ?? $baseline['writable_area'] ?? 'uncertain');

            if (($field['required'] ?? false) && $writable === 'missing') {
                $blocking[] = (string) ($field['label'] ?? $key);
            }

            $schemaFields[] = [
                'key' => $key,
                'label' => $field['label'] ?? $key,
                'type' => $field['type'] ?? FieldType::TEXT,
                'paper_support' => $field['paper_support'] ?? FieldType::PAPER_EXTRACT,
                'options' => $field['options'] ?? null,
                'page' => $baseline['page'] ?? null,
                'bounds' => $baseline['bounds'] ?? null,
                'bounds_source' => $baseline['bounds_source'] ?? 'none',
            ];
        }

        // Signature fields are located from the baseline too so the scan can be
        // checked for presence at review.
        foreach ($baselineByKey as $key => $area) {
            if (($area['paper_support'] ?? null) === FieldType::PAPER_SIGNATURE) {
                $schemaFields[] = [
                    'key' => $key,
                    'label' => $area['label'] ?? $key,
                    'type' => FieldType::SIGNATURE,
                    'paper_support' => FieldType::PAPER_SIGNATURE,
                    'options' => null,
                    'page' => $area['page'] ?? null,
                    'bounds' => $area['bounds'] ?? null,
                    'bounds_source' => $area['bounds_source'] ?? 'none',
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
     * Re-check each table-bound field's present/missing verdict against the
     * populated partial's cell geometry (width-driven, no model). Free-flow
     * fields keep their baseline verdict. Positions are never recomputed here —
     * the baseline holds the accurate rendered bounds.
     *
     * @param  array<string,array<string,mixed>>  $baselineByKey
     * @param  array<int,array<string,mixed>>  $extractable
     * @return array<string,string>  field key => writable_area
     */
    private function recomputeVerdicts(ManualFormSession $session, array $baselineByKey, array $extractable): array
    {
        $paperKeys = [];
        foreach ($extractable as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $paperKeys[$key] = (string) ($field['type'] ?? FieldType::TEXT);
            }
        }

        $docxPath = $this->partialDocxPath($session);
        if ($docxPath === null) {
            return [];
        }

        $verdicts = [];
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
                $verdicts[$key] = (string) $geometry['writable_area'];
            }
        }

        return $verdicts;
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
