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
 * PhpWord inserts those through `setImageValue()` rather than as text.
 */
class DocxTemplateData
{
    /**
     * @param  array<string, mixed>  $payload  the submission payload
     * @param  Collection<int, \App\Models\Form\FormDescription>  $fields
     * @return array{values: array<string, mixed>, images: array<string, string>}
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

        foreach ($fields as $field) {
            $key = (string) ($field->field_key ?? '');

            if ($key === '') {
                continue;
            }

            $type = (string) $field->field_type;
            $options = (array) ($field->field_options ?? []);

            if (in_array($type, [FieldType::IMAGE, FieldType::SIGNATURE, FieldType::MULTI_IMAGE], true)) {
                $path = $this->firstImagePath(SubmissionPresenter::raw($payload, $key), $disk);

                if ($path !== null) {
                    $images[$key] = $path;
                } else {
                    $values[$key] = '';
                }

                continue;
            }

            $values[$key] = $this->textValue($payload, $key, $type, $options);
        }

        foreach (UniversalField::keys() as $universalKey) {
            // System keys (today's date and friends) are resolved for live
            // autofill only; the printed template has never supported them.
            if (UniversalField::isSystemField($universalKey)) {
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

            if (($meta['type'] ?? null) === FieldType::IMAGE) {
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

        return ['values' => $values, 'images' => $images];
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
     * Absolute path of the first stored image behind a value, if it exists.
     */
    private function firstImagePath(mixed $raw, string $disk): ?string
    {
        foreach ((array) $raw as $candidate) {
            $relative = (string) $candidate;

            if ($relative === '') {
                continue;
            }

            if (Storage::disk($disk)->exists($relative)) {
                return Storage::disk($disk)->path($relative);
            }

            // Signature/profile columns sometimes already hold an absolute path.
            if (is_file($relative)) {
                return $relative;
            }
        }

        return null;
    }
}
