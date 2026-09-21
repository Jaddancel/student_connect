<?php

namespace App\Services\ManualForm;

use App\Forms\FieldType;
use App\Models\Form;

/**
 * Builds the field-metadata portion of a manual-filling schema from a form's
 * saved `form_descriptions`. This is the provider-agnostic, deterministic core
 * shared by the template baseline schema and each session schema: `key`, label,
 * type, required flag, paper-support classification, and canonical option
 * value/label pairs for static choice fields.
 *
 * See docs/manual-form-parsing-contract.md sections 2 and 3.
 */
class ManualSchemaBuilder
{
    /**
     * @return array<int,array{
     *     key:string, label:string, type:string, required:bool,
     *     paper_support:string, options:?array<int,array{value:string,label:string}>
     * }>
     */
    public function fieldsFor(Form $form): array
    {
        return $form->fields()
            ->get()
            ->map(fn ($field) => $this->describe($field))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     key:string, label:string, type:string, required:bool,
     *     paper_support:string, options:?array<int,array{value:string,label:string}>
     * }|null
     */
    public function describe(\App\Models\Form\FormDescription $field): ?array
    {
        $type = (string) $field->field_type;
        $key = (string) $field->field_key;

        if ($key === '' || FieldType::isPresentational($type)) {
            return null;
        }

        $options = is_array($field->field_options) ? $field->field_options : [];
        $paperSupport = FieldType::paperSupport($type, $options);

        $optionPairs = null;
        if (FieldType::isOptioned($type) && $paperSupport === FieldType::PAPER_EXTRACT) {
            $optionPairs = FieldType::optionPairs($options);
        }

        return [
            'key' => $key,
            'label' => (string) ($field->field_label ?: $key),
            'type' => $type,
            'required' => (bool) $field->is_required,
            'paper_support' => $paperSupport,
            'options' => $optionPairs,
        ];
    }

    /**
     * The keys of every field a scan can contribute a value or signature for.
     *
     * @param  array<int,array{key:string,paper_support:string}>  $fields
     * @return array<int,string>
     */
    public function extractableKeys(array $fields): array
    {
        return collect($fields)
            ->filter(fn ($f) => in_array(
                $f['paper_support'] ?? '',
                [FieldType::PAPER_EXTRACT, FieldType::PAPER_SIGNATURE],
                true,
            ))
            ->pluck('key')
            ->map(fn ($k) => (string) $k)
            ->all();
    }

    /**
     * The keys that must always be supplied on the digital form.
     *
     * @param  array<int,array{key:string,paper_support:string}>  $fields
     * @return array<int,string>
     */
    public function digitalOnlyKeys(array $fields): array
    {
        return collect($fields)
            ->filter(fn ($f) => ($f['paper_support'] ?? '') === FieldType::PAPER_DIGITAL_ONLY)
            ->pluck('key')
            ->map(fn ($k) => (string) $k)
            ->all();
    }
}
