<?php

namespace App\Forms;

/**
 * Single source of truth for the WYSIWYG form-builder field-type catalog.
 *
 * Each entry declares everything the rest of the system needs to know about a
 * field type: the builder palette label/icon, whether it carries a submission
 * value, whether it stores options, whether it is an uploaded file, and which
 * Laravel validation rules it contributes given a field's config (`field_options`).
 *
 * Keeping this in one place means the builder, the dynamic renderer, the
 * validation builder and the PDF document view stay in agreement.
 */
final class FieldType
{
    public const TEXT = 'text';
    public const TEXTAREA = 'textarea';
    public const NUMBER = 'number';
    public const AGE = 'age';
    public const EMAIL = 'email';
    public const DATE = 'date';
    public const SELECT = 'select';
    public const RADIO = 'radio';
    public const CHECKBOX = 'checkbox';
    public const SIGNATURE = 'signature';
    public const IMAGE = 'image';
    public const FILE = 'file';
    public const HEADING = 'heading';
    public const STATIC_TEXT = 'static-text';

    /**
     * Field types that store no submission value (presentation only).
     *
     * @return string[]
     */
    public static function presentational(): array
    {
        return [self::HEADING, self::STATIC_TEXT];
    }

    /**
     * Field types whose value is an uploaded file path (or array of paths).
     *
     * @return string[]
     */
    public static function fileLike(): array
    {
        return [self::IMAGE, self::FILE];
    }

    /**
     * Field types that store a chooser of options (select/radio/checkbox).
     *
     * @return string[]
     */
    public static function optioned(): array
    {
        return [self::SELECT, self::RADIO, self::CHECKBOX];
    }

    /**
     * The full catalog: machine type => metadata used by the builder palette.
     *
     * @return array<string, array{label:string, icon:string, group:string}>
     */
    public static function catalog(): array
    {
        return [
            self::TEXT        => ['label' => 'Text',        'icon' => 'text',      'group' => 'basic'],
            self::TEXTAREA    => ['label' => 'Paragraph',   'icon' => 'paragraph', 'group' => 'basic'],
            self::NUMBER      => ['label' => 'Number',      'icon' => 'number',    'group' => 'basic'],
            self::AGE         => ['label' => 'Age',         'icon' => 'age',       'group' => 'basic'],
            self::EMAIL       => ['label' => 'Email',       'icon' => 'email',     'group' => 'basic'],
            self::DATE        => ['label' => 'Date',        'icon' => 'date',      'group' => 'basic'],
            self::SELECT      => ['label' => 'Dropdown',    'icon' => 'select',    'group' => 'choice'],
            self::RADIO       => ['label' => 'Radio',       'icon' => 'radio',     'group' => 'choice'],
            self::CHECKBOX    => ['label' => 'Checkbox',    'icon' => 'checkbox',  'group' => 'choice'],
            self::SIGNATURE   => ['label' => 'Signature',   'icon' => 'signature', 'group' => 'media'],
            self::IMAGE       => ['label' => 'Image',       'icon' => 'image',     'group' => 'media'],
            self::FILE        => ['label' => 'File',        'icon' => 'file',      'group' => 'media'],
            self::HEADING     => ['label' => 'Section',     'icon' => 'heading',   'group' => 'layout'],
            self::STATIC_TEXT => ['label' => 'Static text', 'icon' => 'static',    'group' => 'layout'],
        ];
    }

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return array_keys(self::catalog());
    }

    public static function isValid(string $type): bool
    {
        return array_key_exists($type, self::catalog());
    }

    public static function isPresentational(string $type): bool
    {
        return in_array($type, self::presentational(), true);
    }

    public static function isFileLike(string $type): bool
    {
        return in_array($type, self::fileLike(), true);
    }

    public static function isOptioned(string $type): bool
    {
        return in_array($type, self::optioned(), true);
    }

    public static function label(string $type): string
    {
        return self::catalog()[$type]['label'] ?? ucfirst($type);
    }

    /**
     * Build the Laravel validation rules a single field contributes.
     *
     * @param  array<string,mixed>  $options  the field's `field_options`
     * @return array<int,string>
     */
    public static function validationRules(string $type, bool $required, array $options = []): array
    {
        // Presentational fields never validate (they hold no value).
        if (self::isPresentational($type)) {
            return [];
        }

        $rules = [$required ? 'required' : 'nullable'];

        switch ($type) {
            case self::TEXT:
                $rules[] = 'string';
                $rules[] = 'max:'.(int) ($options['max'] ?? 255);
                break;

            case self::TEXTAREA:
                $rules[] = 'string';
                $rules[] = 'max:'.(int) ($options['max'] ?? 10000);
                break;

            case self::EMAIL:
                $rules[] = 'string';
                $rules[] = 'email';
                $rules[] = 'max:255';
                break;

            case self::NUMBER:
            case self::AGE:
                $rules[] = 'numeric';
                $min = $options['min'] ?? ($type === self::AGE ? 0 : null);
                $max = $options['max'] ?? ($type === self::AGE ? 150 : null);
                if ($min !== null && $min !== '') {
                    $rules[] = 'min:'.(float) $min;
                }
                if ($max !== null && $max !== '') {
                    $rules[] = 'max:'.(float) $max;
                }
                break;

            case self::DATE:
                $rules[] = 'date';
                break;

            case self::SELECT:
            case self::RADIO:
                $choices = self::optionValues($options);
                if (! empty($choices)) {
                    $rules[] = 'in:'.implode(',', $choices);
                } else {
                    $rules[] = 'string';
                }
                break;

            case self::CHECKBOX:
                // Checkbox groups submit an array; single checkboxes submit a scalar.
                if (! empty(self::optionValues($options))) {
                    $rules[] = 'array';
                } else {
                    $rules = [$required ? 'accepted' : 'nullable'];
                }
                break;

            case self::SIGNATURE:
                // Captured as a base64 data-URL string (PNG).
                $rules[] = 'string';
                break;

            case self::IMAGE:
            case self::FILE:
                // Files are handled separately as uploads; the presence rule still applies.
                $rules[] = 'file';
                if ($type === self::IMAGE) {
                    $rules[] = 'image';
                }
                $accept = $options['accept'] ?? null;
                if (is_string($accept) && $accept !== '') {
                    $exts = collect(explode(',', $accept))
                        ->map(fn ($e) => ltrim(trim($e), '.'))
                        ->filter()
                        ->implode(',');
                    if ($exts !== '') {
                        $rules[] = 'mimes:'.$exts;
                    }
                }
                $maxKb = (int) ($options['max_kb'] ?? 5120);
                $rules[] = 'max:'.$maxKb;
                break;
        }

        return $rules;
    }

    /**
     * Normalise the choice values for an optioned field.
     *
     * Options are stored as `[['value' => ..., 'label' => ...], ...]` or a plain
     * list of strings; this returns just the values.
     *
     * @param  array<string,mixed>  $options
     * @return array<int,string>
     */
    public static function optionValues(array $options): array
    {
        $raw = $options['options'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(function ($opt) {
                if (is_array($opt)) {
                    return (string) ($opt['value'] ?? $opt['label'] ?? '');
                }
                return (string) $opt;
            })
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();
    }

    /**
     * Normalise options into `[['value'=>..,'label'=>..], ...]` for rendering.
     *
     * @param  array<string,mixed>  $options
     * @return array<int,array{value:string,label:string}>
     */
    public static function optionPairs(array $options): array
    {
        $raw = $options['options'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(function ($opt) {
                if (is_array($opt)) {
                    $value = (string) ($opt['value'] ?? $opt['label'] ?? '');
                    $label = (string) ($opt['label'] ?? $opt['value'] ?? '');
                } else {
                    $value = $label = (string) $opt;
                }
                return ['value' => $value, 'label' => $label];
            })
            ->filter(fn ($p) => $p['value'] !== '')
            ->values()
            ->all();
    }
}
