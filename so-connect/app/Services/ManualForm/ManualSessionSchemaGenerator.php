<?php

namespace App\Services\ManualForm;

use App\Forms\FieldType;
use App\Models\ManualFormSession;

/**
 * Builds a session's parsing schema from its frozen partial PDF: the fields
 * empty at Start time, bound to the writable areas the template baseline
 * located. If a required extractable field has no locatable writable region,
 * the session is blocked (a value written there could never have been printed,
 * so it can't be read back).
 *
 * See docs/manual-form-parsing-contract.md section 3.
 */
class ManualSessionSchemaGenerator
{
    public function generate(string $sessionId): void
    {
        $session = ManualFormSession::find($sessionId);
        if ($session === null || $session->status !== ManualFormSession::STATUS_PREPARING) {
            return;
        }

        $baselineByKey = $this->baselineByKey($session);
        $extractable = (array) $session->extractable_fields;

        $blocking = [];
        $schemaFields = [];
        foreach ($extractable as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $area = $baselineByKey[$key] ?? [];
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
                $schemaFields[] = [
                    'key' => $key,
                    'label' => $area['label'] ?? $key,
                    'type' => FieldType::SIGNATURE,
                    'paper_support' => FieldType::PAPER_SIGNATURE,
                    'options' => null,
                    'page' => $area['page'] ?? null,
                    'bounds' => $area['bounds'] ?? null,
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
