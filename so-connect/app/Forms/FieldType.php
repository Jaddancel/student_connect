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
    public const TIME = 'time';
    public const DATETIME = 'datetime';
    public const SELECT = 'select';
    public const SEARCH = 'search';
    public const RADIO = 'radio';
    public const CHECKBOX = 'checkbox';
    public const SIGNATURE = 'signature';
    public const IMAGE = 'image';
    public const FILE = 'file';
    public const HEADING = 'heading';
    public const STATIC_TEXT = 'static-text';

    // ---- Special kit-scoped types (see App\Forms\FieldKit) ----
    public const ORG_SELECT = 'org-select';
    public const POSITION_SELECT = 'position-select';
    public const PASSWORD = 'password';
    public const ID_SCAN = 'id-scan';

    public const WAIVER_SCAN = 'waiver-scan';
    public const WORKPLAN_EVENTS = 'workplan-events';
    public const TEXT_LIST = 'text-list';
    public const TABLE_INPUT = 'table-input';
    public const COMPUTED = 'computed';
    public const MULTI_IMAGE = 'multi-image';
    public const EVENT_SELECT = 'event-select';
    public const WORKPLAN_SELECT = 'workplan-select';
    public const ACTIVITY_TABLE = 'activity-table';

    /**
     * The officer positions the sign-up position picker offers by default
     * (a field's own `options` config overrides them).
     */
    public const POSITION_OPTIONS = ['President', 'Treasurer', 'Auditor', 'Secretary', 'Others'];

    /**
     * Hard upload allowlists — uploads are limited to JPEG/PNG/HEIC (+ PDF for
     * generic files) no matter what a field's `accept` config says.
     */
    private const IMAGE_EXTENSIONS = ['jpeg', 'jpg', 'png', 'heic', 'heif'];
    private const FILE_EXTENSIONS = ['jpeg', 'jpg', 'png', 'heic', 'heif', 'pdf'];

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
            self::TIME        => ['label' => 'Time',        'icon' => 'date',      'group' => 'basic'],
            self::DATETIME    => ['label' => 'Date + Time', 'icon' => 'date',      'group' => 'basic'],
            self::TEXT_LIST   => ['label' => 'Text list',   'icon' => 'paragraph', 'group' => 'basic'],
            self::SELECT      => ['label' => 'Dropdown',    'icon' => 'select',    'group' => 'choice'],
            self::SEARCH      => ['label' => 'Search bar',  'icon' => 'select',    'group' => 'choice'],
            self::RADIO       => ['label' => 'Radio',       'icon' => 'radio',     'group' => 'choice'],
            self::CHECKBOX    => ['label' => 'Checkbox',    'icon' => 'checkbox',  'group' => 'choice'],
            self::SIGNATURE   => ['label' => 'Signature',   'icon' => 'signature', 'group' => 'media'],
            self::IMAGE       => ['label' => 'Image',       'icon' => 'image',     'group' => 'media'],
            self::FILE        => ['label' => 'File',        'icon' => 'file',      'group' => 'media'],
            self::HEADING     => ['label' => 'Section',     'icon' => 'heading',   'group' => 'layout'],
            self::STATIC_TEXT => ['label' => 'Static text', 'icon' => 'static',    'group' => 'layout'],

            // Special types: only offered on forms whose kit unlocks them
            // (paletteCatalog), but always valid for rendering/validation so
            // existing forms keep working if a kit definition changes.
            self::ORG_SELECT      => ['label' => 'Organization picker', 'icon' => 'select',    'group' => 'special'],
            self::POSITION_SELECT => ['label' => 'Position picker',     'icon' => 'select',    'group' => 'special'],
            self::PASSWORD        => ['label' => 'Password',            'icon' => 'text',      'group' => 'special'],
            self::ID_SCAN         => ['label' => 'ID scan',             'icon' => 'image',     'group' => 'special'],
            self::WAIVER_SCAN     => ['label' => 'Waiver scan',         'icon' => 'image',     'group' => 'special'],
            self::WORKPLAN_EVENTS => ['label' => 'Approved events',     'icon' => 'checkbox',  'group' => 'special'],
            self::TABLE_INPUT     => ['label' => 'Table',               'icon' => 'select',    'group' => 'special'],
            self::COMPUTED        => ['label' => 'Computed value',      'icon' => 'number',    'group' => 'special'],
            self::MULTI_IMAGE     => ['label' => 'Photo set',           'icon' => 'image',     'group' => 'special'],
            self::EVENT_SELECT    => ['label' => 'Event picker',        'icon' => 'select',    'group' => 'special'],
            self::WORKPLAN_SELECT => ['label' => 'Workplan picker',     'icon' => 'select',    'group' => 'special'],
            self::ACTIVITY_TABLE  => ['label' => 'Activity Table',      'icon' => 'table',     'group' => 'special'],
        ];
    }

    /**
     * All special (kit-scoped) types.
     *
     * @return string[]
     */
    public static function specialTypes(): array
    {
        return array_keys(array_filter(self::catalog(), fn ($meta) => $meta['group'] === 'special'));
    }

    public static function isSpecial(string $type): bool
    {
        return (self::catalog()[$type]['group'] ?? '') === 'special';
    }

    /**
     * Whether a field of this type may control another field's visibility
     * condition (it must carry a single comparable value).
     */
    public static function canControlVisibility(string $type): bool
    {
        return ! self::isPresentational($type)
            && ! self::isFileLike($type)
            && ! in_array($type, [
                self::SIGNATURE, self::PASSWORD, self::ID_SCAN, self::COMPUTED,
                self::TEXT_LIST, self::TABLE_INPUT, self::MULTI_IMAGE, self::WORKPLAN_EVENTS,
                self::ACTIVITY_TABLE,
            ], true);
    }

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return array_keys(self::catalog());
    }

    /**
     * The catalog the builder palette offers for NEW fields. `age` is retired
     * from the palette (Number/Date cover it) but stays in catalog()/all() so
     * existing forms keep validating and rendering. Special types are only
     * included when the form's kit unlocks them (see {@see FieldKit}).
     *
     * @return array<string, array{label:string, icon:string, group:string}>
     */
    public static function paletteCatalog(?string $kit = null): array
    {
        $catalog = array_diff_key(self::catalog(), [self::AGE => true]);
        $kitTypes = array_flip(FieldKit::types($kit));

        return array_filter(
            $catalog,
            fn ($meta, $type) => $meta['group'] !== 'special' || isset($kitTypes[$type]),
            ARRAY_FILTER_USE_BOTH,
        );
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
     * The expected signer(s) declared on a signature field: a subset of
     * {@see POSITION_OPTIONS} and a list of `profiles.profile_id` values. Both
     * are validated/normalised so callers can trust the shape.
     *
     * @param  array<string,mixed>  $options  the field's `field_options`
     * @return array{positions: string[], profiles: int[]}
     */
    public static function signatureExpected(array $options): array
    {
        $positions = array_values(array_filter(
            array_map('strval', (array) ($options['expected_positions'] ?? [])),
            fn ($p) => in_array($p, self::POSITION_OPTIONS, true),
        ));

        $profiles = array_values(array_unique(array_filter(
            array_map('intval', (array) ($options['expected_profiles'] ?? [])),
        )));

        return ['positions' => $positions, 'profiles' => $profiles];
    }

    /**
     * Whether a signature field enforces identity at submit time (Compare mode).
     * Compare is on when the admin set `match_mode = 'compare'`, or — when the
     * mode is left unset — implied by any expected signer being configured.
     * Setting `match_mode = 'normal'` is an explicit opt-out that wins even when
     * expected signers are present.
     *
     * @param  array<string,mixed>  $options  the field's `field_options`
     */
    public static function signatureExpectsMatch(array $options): bool
    {
        $mode = $options['match_mode'] ?? null;
        if ($mode === 'compare') {
            return true;
        }
        if ($mode === 'normal') {
            return false;
        }

        $expected = self::signatureExpected($options);

        return $expected['positions'] !== [] || $expected['profiles'] !== [];
    }

    /**
     * Build the Laravel validation rules a single field contributes.
     *
     * @param  array<string,mixed>  $options  the field's `field_options`
     * @return array<int,string>
     */
    public static function validationRules(string $type, bool $required, array $options = []): array
    {
        // Presentational fields never validate (they hold no value), and
        // computed fields are derived server-side — client input is ignored.
        // The activity table is likewise server-snapshotted (never shown on the
        // web form), so any client input under its key is ignored too.
        if (self::isPresentational($type) || $type === self::COMPUTED || $type === self::ACTIVITY_TABLE) {
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

            case self::TIME:
                $rules[] = 'date_format:H:i';
                break;

            case self::DATETIME:
                $rules[] = 'date_format:Y-m-d H:i';
                break;

            case self::SELECT:
            case self::RADIO:
                // A sourced select validates against its scoped source set
                // (values injected as `source_values` at submit time); a static
                // one validates against its hand-typed options.
                if (OptionSource::forField($options) !== null) {
                    $rules[] = self::sourceInRule($options);
                    break;
                }
                $choices = self::optionValues($options);
                if (! empty($choices)) {
                    $rules[] = 'in:'.implode(',', $choices);
                } else {
                    $rules[] = 'string';
                }
                break;

            case self::SEARCH:
                // The search field is a sourced select with a typeahead UI: its
                // submitted value must be one of the scoped source's entries.
                $rules[] = self::sourceInRule($options);
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
                // Files are handled separately as uploads; the presence rule
                // still applies. The type allowlist is enforced server-side
                // regardless of the field's `accept` config. (Laravel's bare
                // `image` rule would reject HEIC, hence explicit mimes.)
                $rules[] = 'file';
                $rules[] = 'mimes:'.implode(',', self::effectiveUploadExtensions($type, $options));
                $maxKb = (int) ($options['max_kb'] ?? 5120);
                $rules[] = 'max:'.$maxKb;
                break;

            // ---- Special kit-scoped types ----
            case self::ORG_SELECT:
                $rules[] = 'integer';
                $rules[] = 'exists:organizations,organization_id';
                break;

            case self::POSITION_SELECT:
                $choices = self::optionValues($options) ?: self::POSITION_OPTIONS;
                $rules[] = 'in:'.implode(',', $choices);
                break;

            case self::PASSWORD:
                $rules[] = 'string';
                $rules[] = 'min:'.(int) ($options['min'] ?? 8);
                $rules[] = 'max:255';
                $rules[] = 'confirmed';
                break;

            case self::ID_SCAN:
                // The field's own value is the scanned/typed student number;
                // the front/back photos ride along as fixed-name uploads.
                $rules[] = 'string';
                $rules[] = 'regex:/^[0-9]+$/';
                $rules[] = 'max:50';
                break;

            case self::WAIVER_SCAN:
                // The captured waiver image as a base64 data-URL string
                // (stored + server re-validated at submit).
                $rules[] = 'string';
                break;

            case self::EVENT_SELECT:
                // "Events" here are the org's approved event plans (the same
                // source the legacy accomplishment report used).
                $rules[] = 'integer';
                $rules[] = 'exists:event_plans,event_plan_id';
                break;

            case self::WORKPLAN_SELECT:
                $rules[] = 'integer';
                $rules[] = 'exists:workplans,workplan_id';
                break;

            case self::WORKPLAN_EVENTS:
            case self::TEXT_LIST:
            case self::TABLE_INPUT:
            case self::MULTI_IMAGE:
                $rules[] = 'array';
                if ($required) {
                    $rules[] = 'min:1';
                }
                if ($type === self::MULTI_IMAGE) {
                    $rules[] = 'max:'.(int) ($options['max_files'] ?? 5);
                }
                break;
        }

        return $rules;
    }

    /**
     * The `in:` rule for a sourced field, restricting the submitted value to the
     * scoped source set. The allowed values are resolved per-request and injected
     * as `source_values` (see {@see \App\Http\Controllers\FormRenderController});
     * an empty set rejects every non-empty value, so a spoofed id outside the
     * submitter's authorized list never passes.
     *
     * @param  array<string,mixed>  $options
     */
    private static function sourceInRule(array $options): string
    {
        $values = array_values(array_filter(
            array_map('strval', (array) ($options['source_values'] ?? [])),
            fn ($v) => $v !== '',
        ));

        return 'in:'.implode(',', $values);
    }

    /**
     * Extra validation rules for the ELEMENTS of array-valued field types,
     * keyed by rule suffix relative to the field key (e.g. `'*' => [...]`,
     * `'*.amount' => [...]`). Merge as `"$fieldKey.$suffix" => $rules`.
     *
     * @param  array<string,mixed>  $options
     * @return array<string,array<int,string>>
     */
    public static function nestedValidationRules(string $type, array $options = []): array
    {
        switch ($type) {
            case self::TEXT_LIST:
                return ['*' => ['nullable', 'string', 'max:500']];

            case self::WORKPLAN_EVENTS:
                return ['*' => ['integer', 'exists:event_plans,event_plan_id']];

            case self::MULTI_IMAGE:
                return ['*' => [
                    'file',
                    'mimes:'.implode(',', self::allowedUploadExtensions(self::IMAGE)),
                    'max:'.(int) ($options['max_kb'] ?? 5120),
                ]];

            case self::TABLE_INPUT:
                $rules = ['*' => ['array']];
                foreach (self::tableColumns($options) as $column) {
                    $columnRules = [($column['required'] ?? false) ? 'required' : 'nullable'];
                    $columnRules = array_merge($columnRules, match ($column['type']) {
                        'number' => ['numeric'],
                        'date' => ['date'],
                        'event-select' => ['integer', 'exists:event_plans,event_plan_id'],
                        default => ['string', 'max:500'],
                    });
                    $rules['*.'.$column['key']] = $columnRules;
                }

                return $rules;
        }

        return [];
    }

    /**
     * Normalised column definitions for a table-input field.
     *
     * @param  array<string,mixed>  $options
     * @return array<int,array{key:string,label:string,type:string,required:bool}>
     */
    public static function tableColumns(array $options): array
    {
        $columns = [];
        foreach ((array) ($options['columns'] ?? []) as $column) {
            if (! is_array($column)) {
                continue;
            }
            $key = trim((string) ($column['key'] ?? ''));
            if ($key === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $key)) {
                continue;
            }
            $type = (string) ($column['type'] ?? 'text');
            $columns[] = [
                'key' => $key,
                'label' => (string) ($column['label'] ?? $key),
                'type' => in_array($type, ['text', 'number', 'date', 'event-select'], true) ? $type : 'text',
                'required' => (bool) ($column['required'] ?? false),
            ];
        }

        return $columns;
    }

    /**
     * Normalised column definitions for an activity-table field. Each column is
     * a snapshot of a New Events form field the admin chose: `key` (the New
     * Events field key), `label` (heading text — kept even after the source
     * field is removed, so the printed table still names its column) and `type`
     * (used to format the resolved cell). Keys are de-duped and order preserved.
     *
     * @param  array<string,mixed>  $options
     * @return array<int,array{key:string,label:string,type:string}>
     */
    public static function activityTableColumns(array $options): array
    {
        $columns = [];
        $seen = [];
        foreach ((array) ($options['columns'] ?? []) as $column) {
            if (! is_array($column)) {
                continue;
            }
            $key = trim((string) ($column['key'] ?? ''));
            if ($key === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $key) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $type = (string) ($column['type'] ?? 'text');
            $columns[] = [
                'key' => $key,
                'label' => (string) ($column['label'] ?? '') !== '' ? (string) $column['label'] : $key,
                'type' => self::isValid($type) ? $type : self::TEXT,
            ];
        }

        return $columns;
    }

    /**
     * The upload extensions a field type may ever accept.
     *
     * @return string[]
     */
    public static function allowedUploadExtensions(string $type): array
    {
        return $type === self::FILE ? self::FILE_EXTENSIONS : self::IMAGE_EXTENSIONS;
    }

    /**
     * The extensions a specific field actually accepts: its `accept` config
     * intersected with the hard allowlist (an empty/invalid accept means the
     * whole allowlist).
     *
     * @param  array<string,mixed>  $options
     * @return string[]
     */
    public static function effectiveUploadExtensions(string $type, array $options = []): array
    {
        $allowed = self::allowedUploadExtensions($type);

        $accept = $options['accept'] ?? null;
        if (! is_string($accept) || trim($accept) === '') {
            return $allowed;
        }

        $picked = collect(explode(',', $accept))
            ->map(fn ($e) => strtolower(ltrim(trim($e), '.')))
            ->map(fn ($e) => $e === 'jpg' ? 'jpeg' : $e)
            ->filter()
            ->unique()
            ->all();

        // jpg/jpeg are one type; expand back so the mimes rule accepts both.
        $effective = array_values(array_filter($allowed, function (string $ext) use ($picked) {
            return in_array($ext === 'jpg' ? 'jpeg' : $ext, $picked, true);
        }));

        return $effective === [] ? $allowed : $effective;
    }

    /**
     * The `accept` attribute for a rendered upload input, mirroring the
     * server-side allowlist.
     *
     * @param  array<string,mixed>  $options
     */
    public static function uploadAcceptAttribute(string $type, array $options = []): string
    {
        $mimes = [
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            'pdf' => 'application/pdf',
        ];

        return collect(self::effectiveUploadExtensions($type, $options))
            ->map(fn (string $ext) => $mimes[$ext] ?? '.'.$ext)
            ->unique()
            ->implode(',');
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
