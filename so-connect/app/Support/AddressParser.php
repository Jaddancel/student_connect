<?php

namespace App\Support;

/**
 * Splits a free-text Philippine address into the columns of `profile_addresses`.
 *
 * Segments are read right-to-left, following the usual
 * "Street, Barangay, Town/City, Province, Country" order:
 *
 *   "123 Rizal St, Brgy. Lahug, Cebu City, Cebu, Philippines"
 *     → barangay "123 Rizal St, Brgy. Lahug", town "Cebu City", province "Cebu"
 *
 * Short addresses fill from the barangay up, and a trailing city is kept as the
 * town (independent cities are often written without a province).
 */
class AddressParser
{
    public const DEFAULT_COUNTRY = 'Philippines';

    private const MAX_LENGTH = 255;

    /**
     * @return array{country: string, province: string, town: string, barangay: string}
     */
    public static function parse(?string $address): array
    {
        $parts = preg_split('/[,;\r\n]+/', (string) $address) ?: [];
        $parts = array_map(fn (string $part) => self::stripPostalCode(trim($part)), $parts);
        $parts = array_values(array_filter($parts, fn (string $part) => $part !== ''));

        if ($parts !== [] && self::isCountry(end($parts))) {
            array_pop($parts);
        }

        $province = '';
        $town = '';

        if (count($parts) >= 2 && self::isCity(end($parts))) {
            $town = array_pop($parts);
        } elseif (count($parts) >= 3) {
            $province = array_pop($parts);
            $town = array_pop($parts);
        } elseif (count($parts) === 2 && self::isCity($parts[0])) {
            $province = array_pop($parts);
            $town = array_pop($parts);
        } elseif (count($parts) === 2) {
            $town = array_pop($parts);
        }

        return [
            'country' => self::DEFAULT_COUNTRY,
            'province' => self::limit($province),
            'town' => self::limit($town),
            'barangay' => self::limit(implode(', ', $parts)),
        ];
    }

    private static function isCountry(string $part): bool
    {
        return (bool) preg_match('/^(republic of the )?(philippines|phil(s|ippines)?\.?|ph)$/i', $part);
    }

    private static function isCity(string $part): bool
    {
        return (bool) preg_match('/\bcity\b|^city of\b/i', $part);
    }

    private static function stripPostalCode(string $part): string
    {
        return trim((string) preg_replace('/(^|\s+)\d{4}$/', '', $part));
    }

    private static function limit(string $value): string
    {
        return mb_substr($value, 0, self::MAX_LENGTH);
    }
}
