<?php

namespace App\Support;

use App\Forms\FieldType;
use App\Models\Profile;

/**
 * Single source of truth for the app's "universal fields" — the canonical set of
 * profile-backed fields that the form builder, the printed-PDF template editor
 * and the OCR/ID-template editor all reference by the SAME key.
 *
 * Because every surface shares these keys, a form field or a template token can
 * be *mapped to a universal field* and then autofill from the signed-in user's
 * {@see \App\Models\Profile}, and the ID scanner can pre-fill any of them (not
 * just `student_id`).
 *
 * This registry is intentionally code-defined and fixed: it mirrors the fixed
 * `profiles` schema. Adding a universal field means adding a profile column and
 * an entry here — there is no admin-editable/EAV path by design.
 *
 * Mirrors the shape of {@see \App\Forms\FieldType}.
 */
final class UniversalField
{
    /**
     * The full catalog: universal key => metadata.
     *
     * - `label`          human label for palette/select UIs
     * - `type`           a {@see FieldType} constant, so the builder/renderer agree
     * - `profile_column` the {@see Profile} column it reads (usually == key)
     * - `group`          palette grouping: name | contact | personal | academic | id
     *
     * @return array<string, array{label:string, type:string, profile_column:string, group:string}>
     */
    public static function catalog(): array
    {
        return [
            'first_name'      => ['label' => 'First Name',        'type' => FieldType::TEXT,     'profile_column' => 'first_name',      'group' => 'name'],
            'middle_name'     => ['label' => 'Middle Name',       'type' => FieldType::TEXT,     'profile_column' => 'middle_name',     'group' => 'name'],
            'last_name'       => ['label' => 'Last Name',         'type' => FieldType::TEXT,     'profile_column' => 'last_name',       'group' => 'name'],

            'contact_number'  => ['label' => 'Contact Number',    'type' => FieldType::TEXT,     'profile_column' => 'contact_number',  'group' => 'contact'],
            'address'         => ['label' => 'Present Address',   'type' => FieldType::TEXTAREA, 'profile_column' => 'address',         'group' => 'contact'],
            'home_address'    => ['label' => 'Home Address',      'type' => FieldType::TEXTAREA, 'profile_column' => 'home_address',    'group' => 'contact'],

            'age'             => ['label' => 'Age',               'type' => FieldType::AGE,      'profile_column' => 'age',             'group' => 'personal'],
            'sex'             => ['label' => 'Sex',               'type' => FieldType::TEXT,     'profile_column' => 'sex',             'group' => 'personal'],
            'religion'        => ['label' => 'Religion',          'type' => FieldType::TEXT,     'profile_column' => 'religion',        'group' => 'personal'],
            'nationality'     => ['label' => 'Nationality',       'type' => FieldType::TEXT,     'profile_column' => 'nationality',     'group' => 'personal'],
            'birthday'        => ['label' => 'Birthday',          'type' => FieldType::DATE,     'profile_column' => 'birthday',        'group' => 'personal'],
            'birthplace'      => ['label' => 'Birthplace',        'type' => FieldType::TEXT,     'profile_column' => 'birthplace',      'group' => 'personal'],
            'parents_guardian' => ['label' => 'Parent / Guardian', 'type' => FieldType::TEXT,    'profile_column' => 'parents_guardian', 'group' => 'personal'],
            'talents_hobbies' => ['label' => 'Talents / Hobbies', 'type' => FieldType::TEXTAREA, 'profile_column' => 'talents_hobbies', 'group' => 'personal'],

            'course_year'     => ['label' => 'Course & Year',     'type' => FieldType::TEXT,     'profile_column' => 'course_year',     'group' => 'academic'],

            'student_id'      => ['label' => 'Student ID',        'type' => FieldType::TEXT,     'profile_column' => 'student_id',      'group' => 'id'],
            'id_photo_front'  => ['label' => 'ID Photo (Front)',  'type' => FieldType::IMAGE,    'profile_column' => 'id_photo_front',  'group' => 'id'],
            'id_photo_back'   => ['label' => 'ID Photo (Back)',   'type' => FieldType::IMAGE,    'profile_column' => 'id_photo_back',   'group' => 'id'],
            'photo'           => ['label' => 'Profile Photo',     'type' => FieldType::IMAGE,    'profile_column' => 'photo',           'group' => 'id'],
        ];
    }

    /**
     * All universal keys (for `Rule::in()` validation and palette iteration).
     *
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::catalog());
    }

    /**
     * @return array{label:string, type:string, profile_column:string, group:string}|null
     */
    public static function get(string $key): ?array
    {
        return self::catalog()[$key] ?? null;
    }

    public static function label(string $key): string
    {
        return self::catalog()[$key]['label'] ?? $key;
    }

    /**
     * Catalog grouped by `group` for grouped palette/`<optgroup>` rendering.
     *
     * @return array<string, array<string, array{label:string, type:string, profile_column:string, group:string}>>
     */
    public static function grouped(): array
    {
        $grouped = [];
        foreach (self::catalog() as $key => $meta) {
            $grouped[$meta['group']][$key] = $meta;
        }

        return $grouped;
    }

    /**
     * Read a universal field's value off a profile — the single place the
     * key→profile indirection lives (drives form autofill and template print).
     *
     * Returns null when there is no profile, no such key, or an empty value.
     * `address` is special-cased because `profiles.address` is a foreign key to
     * `profile_addresses`, not a scalar string.
     */
    public static function valueFor(?Profile $profile, string $key): mixed
    {
        if ($profile === null || ! self::has($key)) {
            return null;
        }

        if ($key === 'address') {
            return self::formattedAddress($profile);
        }

        $column = self::catalog()[$key]['profile_column'];
        $value = $profile->getAttribute($column);

        return ($value === '' || $value === null) ? null : $value;
    }

    /**
     * Format the related present address (`profile_addresses`) into one line, or
     * null when the profile has no linked address.
     */
    private static function formattedAddress(Profile $profile): ?string
    {
        $address = $profile->addressOfUser;
        if ($address === null) {
            return null;
        }

        $parts = array_filter([
            $address->barangay,
            $address->town,
            $address->province,
            $address->country,
        ], static fn ($p) => trim((string) $p) !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }
}
