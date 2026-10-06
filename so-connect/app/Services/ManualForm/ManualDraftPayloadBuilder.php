<?php

namespace App\Services\ManualForm;

use App\Forms\ConditionEvaluator;
use App\Forms\FieldType;
use App\Forms\OptionSource;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a manual-filling draft payload from the values a user has entered when
 * they hit "Start Manual Filling". Unlike the final submit, required fields may
 * be left blank (they will be written by hand), but every value that IS present
 * is still type/option/authorization-validated. Uploaded files are preserved in
 * session-owned temporary storage so they survive resume; passwords are never
 * stored.
 *
 * The returned `known` map (filled, printable values) is what freezes into the
 * partial PDF and becomes `known_values` for parsing.
 */
class ManualDraftPayloadBuilder
{
    /**
     * @return array{
     *     payload: array<string,mixed>,
     *     known: array<string,mixed>,
     *     uploads: array<string,mixed>
     * }
     */
    public function build(Form $form, Request $request, string $sessionId): array
    {
        $fields = $form->fields()->get();

        $visibility = ConditionEvaluator::visibilityMap(
            $fields,
            fn (string $key) => $request->input($key),
        );
        $isHidden = fn (string $key): bool => ($visibility[$key] ?? true) === false;

        // Validate leniently: required becomes nullable so a blank hand-filled
        // field is allowed, but a supplied value is still fully validated.
        $rules = [];
        foreach ($fields as $field) {
            if (FieldType::isPresentational($field->field_type) || $isHidden($field->field_key)) {
                continue;
            }
            $options = (array) ($field->field_options ?? []);
            $source = OptionSource::forField($options);
            if ($source !== null) {
                $options['source_values'] = OptionSource::values($source, $request->user(), scoped: true);
            }
            $fieldRules = FieldType::validationRules($field->field_type, false, $options);
            if (! empty($fieldRules)) {
                $rules[$field->field_key] = $fieldRules;
            }
            foreach (FieldType::nestedValidationRules($field->field_type, $options) as $suffix => $nested) {
                $rules[$field->field_key.'.'.$suffix] = $this->relaxRequired($nested);
            }
        }

        $validated = $request->validate($rules);

        $payload = [];
        $known = [];
        $uploads = [];

        foreach ($fields as $field) {
            $key = $field->field_key;
            $type = $field->field_type;

            if (FieldType::isPresentational($type) || $isHidden($key)) {
                continue;
            }

            // Never persist a plaintext password in a draft — it is re-entered
            // at final review.
            if ($type === FieldType::PASSWORD) {
                continue;
            }

            // Uploaded files/signatures/photos entered up front are kept in the
            // session's own temp area so they restore on resume.
            if (FieldType::isFileLike($type) || $type === FieldType::MULTI_IMAGE) {
                $stored = $this->storeUploads($request, $key, $sessionId, FieldType::isMultiImage($type));
                if ($stored !== null) {
                    $payload[$key] = $stored;
                    $uploads[$key] = $stored;
                }
                continue;
            }

            // Server-snapshotted / relationship / computed fields are digital
            // only — they are not part of a paper draft.
            if (in_array($type, [
                FieldType::ID_SCAN, FieldType::WAIVER_SCAN, FieldType::COMPUTED,
                FieldType::ACTIVITY_TABLE, FieldType::SIGNATURE,
            ], true)) {
                continue;
            }

            $value = $this->normalizeValue($type, $field, $validated[$key] ?? null);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $payload[$key] = $value;
            if (FieldType::isPaperExtractable($type, (array) ($field->field_options ?? []))) {
                $known[$key] = $value;
            }
        }

        return ['payload' => $payload, 'known' => $known, 'uploads' => $uploads];
    }

    /**
     * Normalize a single supplied value the same way the final submit does for
     * the value-bearing (non-upload) field types.
     */
    private function normalizeValue(string $type, \App\Models\Form\FormDescription $field, mixed $raw): mixed
    {
        if ($type === FieldType::TEXT_LIST) {
            return array_values(array_filter(
                array_map(fn ($v) => trim((string) $v), (array) $raw),
                fn ($v) => $v !== '',
            ));
        }

        if ($type === FieldType::TABLE_INPUT) {
            $columns = FieldType::tableColumns((array) ($field->field_options ?? []));
            $rows = [];
            foreach ((array) $raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $clean = [];
                $has = false;
                foreach ($columns as $column) {
                    $cell = $row[$column['key']] ?? null;
                    $cell = is_scalar($cell) ? trim((string) $cell) : null;
                    $clean[$column['key']] = ($cell === '' ? null : $cell);
                    $has = $has || $clean[$column['key']] !== null;
                }
                if ($has) {
                    $rows[] = $clean;
                }
            }

            return $rows;
        }

        if ($type === FieldType::WORKPLAN_EVENTS) {
            return array_values(array_unique(array_map('intval', (array) $raw)));
        }

        if ($type === FieldType::CHECKBOX
            && FieldType::optionValues((array) ($field->field_options ?? [])) === []) {
            return ! empty($raw) ? 1 : 0;
        }

        return $raw;
    }

    /**
     * Store uploaded file(s) for a field into the session's temp area.
     *
     * @return string|array<int,string>|null
     */
    private function storeUploads(Request $request, string $key, string $sessionId, bool $many): string|array|null
    {
        $disk = (string) config('documents.disk', 'public');
        $dir = 'manual-form/'.$sessionId.'/uploads';

        if ($many) {
            $paths = [];
            foreach ((array) $request->file($key, []) as $file) {
                if ($file !== null) {
                    $paths[] = $file->store($dir, $disk);
                }
            }

            return $paths !== [] ? $paths : null;
        }

        if ($request->hasFile($key)) {
            return $request->file($key)->store($dir, $disk);
        }

        return null;
    }

    /**
     * @param  array<int,string>  $rules
     * @return array<int,string>
     */
    private function relaxRequired(array $rules): array
    {
        return array_map(fn ($r) => $r === 'required' ? 'nullable' : $r, $rules);
    }
}
