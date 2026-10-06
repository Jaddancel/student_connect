<?php

namespace App\Forms;

use App\Models\Organization;
use App\Models\Profile;
use App\Support\OrganizationField;
use App\Support\UniversalField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Flattens a form submission into the `{{field_key}}` => value map that
 * {@see \App\Services\DocxTemplateService::populate()} fills a Word template
 * with.
 *
 * This is the DOCX counterpart to {@see PdfTemplateRenderer}, which does the
 * same job against HTML nodes for the dompdf path. The per-type value
 * semantics are deliberately kept in step with that class: a field must print
 * the same whichever pipeline rendered it.
 *
 * Values that are pictures (signatures, uploads) come back separately, because
 * PhpWord inserts those through `setImageValue()` rather than as text. Table
 * fields also report their ordered column labels under `tables`, which
 * {@see \App\Support\DocxChartFiller} uses to lay a chart's spreadsheet out
 * like the table.
 */
class DocxTemplateData
{
    /**
     * @param  array<string, mixed>  $payload  the submission payload
     * @param  Collection<int, \App\Models\Form\FormDescription>  $fields
     * @return array{values: array<string, mixed>, images: array<string, string|array<int,string>>, tables: array<string, array<string, string>>}
     */
    public function build(
        array $payload,
        Collection $fields,
        ?Profile $profile = null,
        ?Organization $organization = null,
        ?string $disk = null,
    ): array {
        $disk ??= (string) config('documents.disk', 'public');

        $values = [];
        $images = [];
        $tables = [];

        foreach ($fields as $field) {
            $key = (string) ($field->field_key ?? '');

            if ($key === '') {
                continue;
            }

            $type = (string) $field->field_type;
            $options = (array) ($field->field_options ?? []);

            if (in_array($type, [FieldType::IMAGE, FieldType::SIGNATURE, FieldType::MULTI_IMAGE], true)) {
                $raw = SubmissionPresenter::raw($payload, $key);
                $path = FieldType::isMultiImage($type)
                    ? $this->imagePaths($raw, $disk)
                    : $this->firstImagePath($raw, $disk);

                if ($path !== null && $path !== []) {
                    $images[$key] = $path;
                } else {
                    $values[$key] = '';
                }

                continue;
            }

            // Activity Table: the stored snapshot is a list of row objects keyed
            // by column key. Emit one parallel array per column under a dotted
            // `{{key.col#}}` token (normalizeData collapses '.'→'_' exactly as the
            // token side does), so the row-repeat machinery clones one table row
            // per approved activity. A bare `{{key}}`/`{{key#}}` falls back to the
            // first column's values.
            if ($type === FieldType::ACTIVITY_TABLE) {
                $rows = array_values(array_filter((array) ($payload[$key] ?? []), 'is_array'));
                $columns = FieldType::activityTableColumns($options);
                foreach ($columns as $column) {
                    $values[$key.'.'.$column['key']] = array_map(
                        fn ($row) => (string) ($row[$column['key']] ?? ''),
                        $rows,
                    );
                }
                $first = $columns[0]['key'] ?? null;
                $values[$key] = $first
                    ? array_map(fn ($row) => (string) ($row[$first] ?? ''), $rows)
                    : [];

                continue;
            }

            // Table field: the stored value is a list of row objects keyed by
            // column key. Like the Activity Table, emit one parallel array per
            // column (and per-row computed column) under a dotted `{{key.col#}}`
            // token so the row-repeat machinery clones one table row per entry;
            // event-select columns print "Title — Date" to match the HTML path.
            if ($type === FieldType::TABLE_INPUT) {
                $rows = array_values(array_filter((array) ($payload[$key] ?? []), 'is_array'));
                $columns = FieldType::tableColumns($options);

                $cellValues = function (string $columnKey, ?string $columnType) use ($rows) {
                    return array_map(function ($row) use ($columnKey, $columnType) {
                        $cell = $row[$columnKey] ?? '';

                        return $columnType === 'event-select'
                            ? SpecialFieldLabel::eventPlanTitleWithDate($cell)
                            : (string) $cell;
                    }, $rows);
                };

                $tables[$key] = [];
                foreach ($columns as $column) {
                    $values[$key.'.'.$column['key']] = $cellValues($column['key'], $column['type']);
                    $tables[$key][$column['key']] = $column['label'];
                }

                $rowTotal = FieldType::tableRowTotal($options);
                if ($rowTotal !== null && ! array_key_exists($rowTotal['key'], $tables[$key])) {
                    $values[$key.'.'.$rowTotal['key']] = $cellValues($rowTotal['key'], null);
                    $tables[$key][$rowTotal['key']] = $rowTotal['label'];
                }

                $firstColumn = $columns[0] ?? null;
                $values[$key] = $firstColumn
                    ? $cellValues($firstColumn['key'], $firstColumn['type'])
                    : [];

                continue;
            }

            $values[$key] = $this->textValue($payload, $key, $type, $options);
        }

        foreach (UniversalField::keys() as $universalKey) {
            // System keys (today's date and friends) are resolved for live
            // autofill only; the printed template has never supported them.
            // Form-derived keys (e.g. age-from-birthday) have no profile/org
            // value, so they'd only emit a dead `profile.<key>` token.
            if (UniversalField::isSystemField($universalKey) || UniversalField::isFormDerived($universalKey)) {
                continue;
            }

            $value = UniversalField::isOrgField($universalKey)
                ? OrganizationField::value($organization, $universalKey)
                : UniversalField::valueFor($profile, $universalKey);

            // Namespaced to match the `{{profile.key}}` placeholders that
            // DocxTemplateService writes when exporting universal tokens.
            $token = 'profile.'.$universalKey;

            if ($value === null || $value === '') {
                $values[$token] = '';

                continue;
            }

            $meta = UniversalField::get($universalKey);

            if (in_array($meta['type'] ?? null, [FieldType::IMAGE, FieldType::SIGNATURE], true)) {
                $path = $this->firstImagePath($value, $disk);

                if ($path !== null) {
                    $images[$token] = $path;
                } else {
                    $values[$token] = '';
                }

                continue;
            }

            $values[$token] = (string) $value;
        }

        return ['values' => $values, 'images' => $images, 'tables' => $tables];
    }

    /**
     * The printed text for one field.
     *
     * Array-valued fields are returned as arrays rather than pre-joined: a
     * repeating `{{key#}}` placeholder then gets one row per item, while a
     * plain `{{key}}` still collapses to a comma-separated list downstream.
     */
    private function textValue(array $payload, string $key, string $type, array $options): mixed
    {
        // Stored hashed, and never appropriate to print.
        if ($type === FieldType::PASSWORD) {
            return '';
        }

        if (in_array($type, [FieldType::ORG_SELECT, FieldType::EVENT_SELECT, FieldType::WORKPLAN_EVENTS], true)) {
            return SpecialFieldLabel::forField($type, $payload[$key] ?? null);
        }

        if ($type === FieldType::TEXT_LIST) {
            return array_values(array_filter(
                array_map('strval', (array) ($payload[$key] ?? [])),
                fn ($item) => $item !== '',
            ));
        }

        if ($type === FieldType::FILE) {
            $names = [];

            foreach ((array) SubmissionPresenter::raw($payload, $key) as $path) {
                $path = (string) $path;

                if ($path !== '') {
                    $names[] = basename($path);
                }
            }

            return $names;
        }

        if ($type === FieldType::CHECKBOX) {
            $optionValues = FieldType::optionValues($options);

            // A lone checkmark is a yes/no. Word has no dependable ballot glyph
            // across fonts, so this prints words rather than boxes — the one
            // place the DOCX output reads differently from the dompdf one.
            if ($optionValues === []) {
                return SubmissionPresenter::isChecked($payload, $key) ? 'Yes' : 'No';
            }

            $selected = array_map('strval', (array) SubmissionPresenter::raw($payload, $key));
            $rendered = [];

            foreach (FieldType::optionPairs($options) as $pair) {
                $mark = in_array($pair['value'], $selected, true) ? '[x]' : '[ ]';
                $rendered[] = $mark.' '.$pair['label'];
            }

            return $rendered;
        }

        return SubmissionPresenter::display($payload, $key, $type, $options);
    }

    /**
     * Absolute paths of the stored images behind a value, if they exist.
     *
     * @return array<int,string>
     */
    private function imagePaths(mixed $raw, string $disk): array
    {
        $paths = [];
        foreach ((array) $raw as $candidate) {
            $relative = (string) $candidate;

            if ($relative === '') {
                continue;
            }

            if (Storage::disk($disk)->exists($relative)) {
                $paths[] = Storage::disk($disk)->path($relative);

                continue;
            }

            // Signature/profile columns sometimes already hold an absolute path.
            if (is_file($relative)) {
                $paths[] = $relative;
            }
        }

        return $paths;
    }

    /**
     * Absolute path of the first stored image behind a value, if it exists.
     */
    private function firstImagePath(mixed $raw, string $disk): ?string
    {
        return $this->imagePaths($raw, $disk)[0] ?? null;
    }
}
