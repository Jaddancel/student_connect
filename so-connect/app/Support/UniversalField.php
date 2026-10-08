<?php

namespace App\Support;

use App\Forms\FieldType;
use App\Models\Profile;
use App\Models\Semester;

/**
 * Single source of truth for the app's "universal fields" — canonical keys the
 * form builder, the printed-PDF template editor and the OCR/ID-template editor
 * all reference by the SAME key so a field/token can be *mapped to a universal
 * field* and autofilled.
 *
 * Three sources of value:
 *  - `source: 'profile'` — read from the signed-in user's {@see Profile} (the
 *    fixed, code-defined profile subset). `profile_column` names the column.
 *  - `source: 'org'` — resolved from the submitter's organization at render time
 *    (current officeholders, or the org's adviser list). These have NO
 *    `profile_column`; resolution lives in {@see OrganizationField}.
 *  - `source: 'system'` — resolved from the clock/school-calendar at render
 *    time (today's date/time/year, the current school year). Depend on
 *    neither the submitter nor their organization; resolution lives in
 *    {@see systemValue()}. Which field types the builder offers each one on
 *    is a small hardcoded map in form-builder.js's `systemAutofillOptions()`
 *    (mirrors how `isNumeric`/`supportsAutofillNow` gate other per-type UI
 *    there), not modeled here.
 *
 * The registry is intentionally code-defined: profile fields mirror the fixed
 * `profiles` schema; org fields are derived, not per-tenant EAV.
 */
final class UniversalField
{
    /**
     * The full catalog: universal key => metadata.
     *
     * - `label`          human label for palette/select UIs
     * - `type`           a {@see FieldType} constant
     * - `source`         'profile' | 'org'
     * - `profile_column` the {@see Profile} column (profile source only)
     * - `group`          palette grouping: name | contact | academic | personal | organization | officer_contact
     * - `format`         optional presentation format applied in valueFor() (e.g. `date:F j, Y`)
     *
     * @return array<string, array{label:string, type:string, source:string, group:string, profile_column?:string, format?:string}>
     */
    public static function catalog(): array
    {
        return [
            'first_name'    => ['label' => 'First Name',   'type' => FieldType::TEXT,     'source' => 'profile', 'profile_column' => 'first_name',   'group' => 'name'],
            'middle_name'   => ['label' => 'Middle Name',  'type' => FieldType::TEXT,     'source' => 'profile', 'profile_column' => 'middle_name',  'group' => 'name'],
            'last_name'     => ['label' => 'Last Name',    'type' => FieldType::TEXT,     'source' => 'profile', 'profile_column' => 'last_name',    'group' => 'name'],

            'home_address'  => ['label' => 'Home Address', 'type' => FieldType::TEXTAREA, 'source' => 'profile', 'profile_column' => 'home_address', 'group' => 'contact'],

            'course'        => ['label' => 'Course',        'type' => FieldType::TEXT,    'source' => 'profile', 'profile_column' => 'course',       'group' => 'academic'],
            'year_section'  => ['label' => 'Year & Section','type' => FieldType::TEXT,    'source' => 'profile', 'profile_column' => 'year_section', 'group' => 'academic'],

            'sex'           => ['label' => 'Sex',          'type' => FieldType::TEXT,     'source' => 'profile', 'profile_column' => 'sex',          'group' => 'personal'],
            'religion'      => ['label' => 'Religion',     'type' => FieldType::TEXT,     'source' => 'profile', 'profile_column' => 'religion',     'group' => 'personal'],
            // `birthday` is stored as a date but presented spelled-out (e.g. "January 5, 2000");
            // `format` is applied in valueFor() so every consumer gets the same rendering.
            'birthday'      => ['label' => 'Birthday',     'type' => FieldType::TEXT,     'source' => 'profile', 'profile_column' => 'birthday',     'group' => 'personal', 'format' => 'date:F j, Y'],
            // The stored value is a disk-relative path to the saved signature PNG;
            // signature fields render it as an image preview, never as text.
            'signature'     => ['label' => 'Signature',    'type' => FieldType::SIGNATURE, 'source' => 'profile', 'profile_column' => 'signature_path', 'group' => 'personal'],

            // Form-derived: not read from a profile/org/system, but computed from a
            // sibling field on the SAME form (the birthday date field). Unique to
            // the Sign Up builder — at sign-up there is no profile yet, so the age
            // is computed live client-side and authoritatively recomputed at submit.
            'age_from_birthday' => ['label' => 'Age - Computed from Birthday', 'type' => FieldType::NUMBER, 'source' => 'form', 'group' => 'personal'],

            // Organization-scoped: resolved from the submitter's org (see OrganizationField).
            'org_name'      => ['label' => 'Organization Name',      'type' => FieldType::TEXT,   'source' => 'org', 'group' => 'organization'],
            'adviser'       => ['label' => 'Adviser',                'type' => FieldType::SELECT, 'source' => 'org', 'group' => 'organization'],
            'org_president'  => ['label' => 'Organization President', 'type' => FieldType::TEXT,   'source' => 'org', 'group' => 'organization'],
            'org_auditor'    => ['label' => 'Organization Auditor',   'type' => FieldType::TEXT,   'source' => 'org', 'group' => 'organization'],
            'org_treasurer'  => ['label' => 'Organization Treasurer', 'type' => FieldType::TEXT,   'source' => 'org', 'group' => 'organization'],
            'org_secretary'  => ['label' => 'Organization Secretary', 'type' => FieldType::TEXT,   'source' => 'org', 'group' => 'organization'],
            'org_category'   => ['label' => 'Organization Category',  'type' => FieldType::TEXT,   'source' => 'org', 'group' => 'organization'],
            'org_president_signature' => ['label' => 'Organization President Signature', 'type' => FieldType::SIGNATURE, 'source' => 'org', 'group' => 'organization'],
            'org_treasurer_signature' => ['label' => 'Organization Treasurer Signature', 'type' => FieldType::SIGNATURE, 'source' => 'org', 'group' => 'organization'],
            'org_auditor_signature' => ['label' => 'Organization Auditor Signature', 'type' => FieldType::SIGNATURE, 'source' => 'org', 'group' => 'organization'],
            'org_secretary_signature' => ['label' => 'Organization Secretary Signature', 'type' => FieldType::SIGNATURE, 'source' => 'org', 'group' => 'organization'],
            // The same officeholders' profile contact numbers, normalized to local
            // numbers (e.g. 09171234567). Their own group: the Step 1 builder offers
            // them only on text fields.
            'org_president_contact' => ['label' => 'Organization President Contact Number', 'type' => FieldType::TEXT, 'source' => 'org', 'group' => 'officer_contact'],
            'org_treasurer_contact' => ['label' => 'Organization Treasurer Contact Number', 'type' => FieldType::TEXT, 'source' => 'org', 'group' => 'officer_contact'],
            'org_auditor_contact'   => ['label' => 'Organization Auditor Contact Number',   'type' => FieldType::TEXT, 'source' => 'org', 'group' => 'officer_contact'],
            'org_secretary_contact' => ['label' => 'Organization Secretary Contact Number', 'type' => FieldType::TEXT, 'source' => 'org', 'group' => 'officer_contact'],

            // System-scoped: resolved from the clock/school calendar at render time
            // (see systemValue()), not from the submitter or their organization.
            'current_date'         => ['label' => 'Current Date',        'type' => FieldType::DATE,     'source' => 'system', 'group' => 'current'],
            'current_time'         => ['label' => 'Time',                'type' => FieldType::TIME,     'source' => 'system', 'group' => 'current'],
            'current_datetime'     => ['label' => 'Date/Time',           'type' => FieldType::DATETIME, 'source' => 'system', 'group' => 'current'],
            'current_year'         => ['label' => 'Year',                'type' => FieldType::NUMBER,   'source' => 'system', 'group' => 'current'],
            'current_school_year'  => ['label' => 'Current School Year', 'type' => FieldType::TEXT,     'source' => 'system', 'group' => 'current'],
            'current_semester'     => ['label' => 'Current Semester',    'type' => FieldType::TEXT,     'source' => 'system', 'group' => 'current'],
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

    /**
     * Keys limited to one source ('profile' | 'org').
     *
     * @return string[]
     */
    public static function keysBySource(string $source): array
    {
        return array_keys(array_filter(
            self::catalog(),
            static fn (array $meta) => ($meta['source'] ?? 'profile') === $source,
        ));
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::catalog());
    }

    /**
     * @return array{label:string, type:string, source:string, group:string, profile_column?:string, format?:string}|null
     */
    public static function get(string $key): ?array
    {
        return self::catalog()[$key] ?? null;
    }

    public static function label(string $key): string
    {
        return self::catalog()[$key]['label'] ?? $key;
    }

    public static function source(string $key): ?string
    {
        return self::catalog()[$key]['source'] ?? null;
    }

    public static function isOrgField(string $key): bool
    {
        return self::source($key) === 'org';
    }

    public static function isSystemField(string $key): bool
    {
        return self::source($key) === 'system';
    }

    /**
     * Whether a key is derived from another field on the same form (source
     * 'form'), rather than from a profile/org/system value. These have no
     * profile/render-time value — they are computed client-side and recomputed
     * at submit — so consumers that iterate the whole catalog skip them.
     */
    public static function isFormDerived(string $key): bool
    {
        return self::source($key) === 'form';
    }

    /**
     * Catalog grouped by `group` for grouped palette/`<optgroup>` rendering.
     *
     * @return array<string, array<string, array<string,mixed>>>
     */
    public static function grouped(): array
    {
        return self::groupCatalog(self::catalog());
    }

    /**
     * Grouped catalog limited to one source — used where org fields don't belong
     * (e.g. the ID-template zone-field mapping, which targets profile fields).
     *
     * @return array<string, array<string, array<string,mixed>>>
     */
    public static function groupedBySource(string $source): array
    {
        return self::groupCatalog(array_filter(
            self::catalog(),
            static fn (array $meta) => ($meta['source'] ?? 'profile') === $source,
        ));
    }

    /**
     * @param  array<string, array<string,mixed>>  $catalog
     * @return array<string, array<string, array<string,mixed>>>
     */
    private static function groupCatalog(array $catalog): array
    {
        $grouped = [];
        foreach ($catalog as $key => $meta) {
            $grouped[$meta['group']][$key] = $meta;
        }

        return $grouped;
    }

    /**
     * Read a profile-backed universal field's value off a profile — the single
     * place the key→profile indirection lives. Org-source keys always return null
     * here (resolve them via {@see OrganizationField}).
     *
     * Returns null when there is no profile, no such key, a non-profile key, or an
     * empty value.
     */
    public static function valueFor(?Profile $profile, string $key): mixed
    {
        $meta = self::get($key);
        if ($profile === null || $meta === null || ($meta['source'] ?? 'profile') !== 'profile') {
            return null;
        }

        $value = $profile->getAttribute($meta['profile_column']);
        if ($value === '' || $value === null) {
            return null;
        }

        // Optional presentation formatting (e.g. `date:F j, Y` spells a birthday out).
        $format = $meta['format'] ?? null;
        if (is_string($format) && str_starts_with($format, 'date:')) {
            try {
                return \Illuminate\Support\Carbon::parse((string) $value)->format(substr($format, 5));
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * Resolve a `source: 'system'` key's value at render time — the current
     * date/time/year or school year. None of these depend on a profile or
     * organization, so unlike {@see valueFor()} this takes no arguments.
     * Returns null for a key that isn't a system field.
     */
    public static function systemValue(string $key): ?string
    {
        return match ($key) {
            'current_date' => now()->toDateString(),
            'current_time' => now()->format('H:i'),
            'current_datetime' => now()->format('Y-m-d H:i'),
            'current_year' => now()->format('Y'),
            'current_school_year' => Semester::currentSchoolYear(),
            'current_semester' => Semester::currentSemesterLabel(),
            default => null,
        };
    }
}
