<?php

namespace App\Support;

/**
 * A field-shaped value object for building printed-template tokens from a source
 * that isn't a persisted {@see \App\Models\Field} — namely a non-persistent
 * draft snapshot held in the file cache while the form builder is on Step 2.
 *
 * {@see \App\Http\Controllers\Admin\FormPrintTemplateController::tokensFor()}
 * reads only `field_key`, `field_label`, `field_type` and `field_options` off
 * each item, so exposing those four as public readonly properties lets a draft
 * descriptor stand in for an Eloquent field with no other changes.
 */
readonly class FieldTokenSource
{
    /**
     * @param  array<string, mixed>  $field_options
     */
    public function __construct(
        public string $field_key,
        public string $field_label,
        public string $field_type,
        public array $field_options,
    ) {}

    /**
     * Build an ordered list of token sources from draft field descriptors.
     *
     * The draft already stores fields in display order, so this preserves the
     * array order rather than re-sorting the way the persisted query does.
     *
     * @param  array<int, array<string, mixed>>  $descriptors
     * @return array<int, self>
     */
    public static function fromDraft(array $descriptors): array
    {
        $out = [];

        foreach ($descriptors as $descriptor) {
            $out[] = new self(
                (string) ($descriptor['field_key'] ?? ''),
                (string) ($descriptor['field_label'] ?? ''),
                (string) ($descriptor['field_type'] ?? ''),
                (array) ($descriptor['field_options'] ?? []),
            );
        }

        return $out;
    }
}
