<?php

namespace App\Support;

use App\Services\OcrClient;

/**
 * Best-effort splitter turning a person's full name — however they typed it —
 * into the canonical {first_name, middle_name, last_name} the `profiles` table
 * stores. Used when an auto-created signature profile must be filed under a
 * name captured from a form.
 *
 * The heavy lifting (comma form "Last, First Middle", plain form
 * "First Middle Last", surname particles, generational suffixes, middle
 * initials) already lives in {@see OcrClient::splitFullName}, which is exercised
 * by the ID scanner. This wrapper reuses it and guarantees all three keys are
 * present — `middle_name` is NOT NULL on the profiles table, so it defaults to
 * an empty string rather than being absent.
 */
final class NameParser
{
    /**
     * @return array{first_name:string, middle_name:string, last_name:string}
     */
    public static function split(string $full): array
    {
        $parts = OcrClient::splitFullName($full);

        return [
            'first_name' => trim((string) ($parts['first_name'] ?? '')),
            'middle_name' => trim((string) ($parts['middle_name'] ?? '')),
            'last_name' => trim((string) ($parts['last_name'] ?? '')),
        ];
    }
}
