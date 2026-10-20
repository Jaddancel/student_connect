<?php

namespace App\Forms;

use App\Models\Form\FormDescription;

/**
 * Allow-list validation for builder-form fields whose meaning is known from
 * their key (OWASP: "apply an allow list of allowed characters"). Generic
 * FieldType rules only check shape (string, max length, date…); these pin
 * names, phone numbers and school years to the characters they can actually
 * contain, so free-form payloads (SQL fragments, markup…) are rejected at the
 * edge on top of parameterized persistence.
 *
 * Applied on final submission only; manual-scan drafts stay lenient.
 */
final class FieldInputPolicy
{
    /** Letters (any script, incl. combining marks), spaces, . ' ’ , - */
    public const NAME = '/^[\pL\pM][\pL\pM\s.\'’,-]*$/u';

    /** Optional +, then digits with spaces ( ) / - separators. */
    public const PHONE = '/^\+?[0-9(][0-9\s()\/-]{5,30}$/';

    /** e.g. 2025-2026 or 2025–2026 */
    public const SCHOOL_YEAR = '/^\d{4}\s*[-–]\s*\d{4}$/u';

    private const BY_KEY = [
        'first_name' => self::NAME,
        'middle_name' => self::NAME,
        'last_name' => self::NAME,
        'contact_number' => self::PHONE,
        'school_year' => self::SCHOOL_YEAR,
    ];

    private const MESSAGES = [
        self::NAME => 'may only contain letters, spaces, periods, apostrophes, commas and hyphens.',
        self::PHONE => 'must be a phone number (digits, optionally with +, spaces, hyphens or parentheses).',
        self::SCHOOL_YEAR => 'must be a school year such as 2025-2026.',
    ];

    /**
     * Extra rules for the field, or [] when its key has no known format.
     *
     * @return array<int, string>
     */
    public static function rulesFor(FormDescription $field): array
    {
        $pattern = self::patternFor($field);

        return $pattern === null ? [] : ['regex:'.$pattern];
    }

    /**
     * Validation message for the field's allow-list rule, keyed for
     * Validator::make()/->validate() custom messages.
     *
     * @return array<string, string>
     */
    public static function messagesFor(FormDescription $field): array
    {
        $pattern = self::patternFor($field);

        return $pattern === null
            ? []
            : [$field->field_key.'.regex' => 'The '.($field->field_label ?: str_replace('_', ' ', $field->field_key)).' '.self::MESSAGES[$pattern]];
    }

    private static function patternFor(FormDescription $field): ?string
    {
        // Only free-text inputs: selects, dates, emails… already have stricter rules.
        if ($field->field_type !== FieldType::TEXT) {
            return null;
        }

        $key = (string) ($field->universal_key ?: $field->field_key);

        return self::BY_KEY[$key] ?? null;
    }
}
