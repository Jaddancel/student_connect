<?php

namespace App\Services;

use App\Helpers\FormTemplateHelper;

/**
 * Deterministically derives form-field candidates from the clean, layout-aware
 * text produced by FormTemplateHelper::extractTextFromDocx().
 *
 * The DOCX text is already structured — one line per paragraph, table rows
 * rendered as "cellA | cellB | cellC", colons and underscore-blanks preserved —
 * so most field detection can be done in PHP without an LLM. This guarantees a
 * complete, non-empty, correctly-typed baseline that never depends on a small
 * model producing valid whole-document JSON. LlmService::refineFields() may then
 * prune/retype this list as a best-effort pass, but can only ever subtract.
 */
class FieldCandidateExtractor
{
    /**
     * Ordered keyword map for field-type inference. First group whose keyword
     * appears in the (lowercased) label wins. Order matters: more specific
     * groups (email, date) come before broader ones (number, textarea).
     *
     * @var array<string, array<int, string>>
     */
    private const TYPE_KEYWORDS = [
        'email' => ['email', 'e-mail'],
        'date' => ['date', 'birth', 'dob', 'effective', 'expiry', 'expiration', 'issued', 'filed', 'deadline'],
        'number' => ['age', 'year', 'number', 'no.', 'count', 'quantity', 'qty', 'amount', 'total', 'grade', 'units', 'gpa', 'phone', 'mobile', 'contact number', 'zip'],
        'textarea' => ['address', 'objective', 'description', 'remarks', 'justification', 'reason', 'comment', 'details', 'narrative', 'summary', 'purpose', 'explain'],
        'checkbox' => ['yes/no', 'agree', 'consent', 'check one', 'male/female', '[ ]', '☐'],
    ];

    /**
     * Words that mark a field as optional rather than required.
     *
     * @var array<int, string>
     */
    private const OPTIONAL_MARKERS = ['optional', 'if any', 'if applicable'];

    /**
     * Phrases that identify office-staff signature/approval lines (excluded).
     */
    private const SIGNATURE_PATTERN = '/\b(signature|signed|approved by|noted by|received by|verified by|conforme|recommending approval)\b/i';

    /**
     * @return array<int, array{label: string, field_key: string, field_type: string, is_required: bool, field_order: int}>
     */
    public function extract(string $structuredText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $structuredText) ?: [];

        $fields = [];
        $seenKeys = [];
        $order = 0;
        $lineIndex = 0;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            $lineIndex++;

            if ($line === '') {
                continue;
            }

            // Table rows: each qualifying cell is its own candidate.
            if (str_contains($line, '|')) {
                foreach (explode('|', $line) as $cell) {
                    $label = $this->labelFromCell(trim($cell));
                    if ($label !== null) {
                        $this->push($fields, $seenKeys, $order, $label);
                    }
                }

                continue;
            }

            // Exclude a leading title line: the first non-empty line with no
            // field markers (colon / underscore) that looks like a heading.
            if ($lineIndex <= 2 && $this->looksLikeTitle($line)) {
                continue;
            }

            $label = $this->labelFromLine($line);
            if ($label !== null) {
                $this->push($fields, $seenKeys, $order, $label);
            }
        }

        return $fields;
    }

    /**
     * Public so LlmService can share the exact same enum/keyword mapping when
     * validating refined fields.
     */
    public function inferType(string $label): string
    {
        $haystack = strtolower($label);

        foreach (self::TYPE_KEYWORDS as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $type;
                }
            }
        }

        return 'text';
    }

    /**
     * Derive a field label from a non-table line, or null if it is not a field.
     */
    private function labelFromLine(string $line): ?string
    {
        // Rule B — colon labels: "Student Name:", "Date of Birth: ____".
        $colon = strpos($line, ':');
        if ($colon !== false && $colon >= 1 && $colon <= 60) {
            $label = trim(substr($line, 0, $colon));

            return $this->cleanLabel($label);
        }

        // Rule C — underscore/blank fill: "Date Filed _______".
        if (preg_match('/^(.*?)_{2,}/', $line, $m)) {
            $label = trim($m[1]);

            return $this->cleanLabel($label);
        }

        // Rule E — standalone short noun-phrase label: "Email Address".
        if ($this->isShortLabel($line) && ! $this->isExcluded($line)) {
            return $this->cleanLabel($line);
        }

        return null;
    }

    /**
     * Derive a label from a single table cell, or null if the cell is not a field.
     */
    private function labelFromCell(string $cell): ?string
    {
        if ($cell === '' || $this->isExcluded($cell)) {
            return null;
        }

        // A cell may itself carry a colon ("Name:") or underscores.
        $colon = strpos($cell, ':');
        if ($colon !== false && $colon >= 1) {
            $cell = trim(substr($cell, 0, $colon));
        } elseif (preg_match('/^(.*?)_{2,}/', $cell, $m) && trim($m[1]) !== '') {
            $cell = trim($m[1]);
        }

        // Skip pure-numeric or empty cells (table values, not labels).
        if ($cell === '' || is_numeric($cell)) {
            return null;
        }

        // A long prose sentence inside a cell is boilerplate, not a field.
        if ($this->isLongSentence($cell)) {
            return null;
        }

        return $this->cleanLabel($cell);
    }

    /**
     * Normalise a raw label and reject it (null) if empty after cleaning.
     */
    private function cleanLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        // Strip a trailing parenthetical hint like "(optional)" from the label
        // text but keep the surrounding words; requiredness is read separately.
        $clean = trim($label, " \t.-–—:*");

        if ($clean === '' || $this->isExcluded($clean)) {
            return null;
        }

        // Reject leftover prose that slipped through (e.g. a colon mid-sentence).
        if ($this->isLongSentence($clean)) {
            return null;
        }

        return $clean;
    }

    private function isShortLabel(string $line): bool
    {
        $words = str_word_count($line);

        return $words >= 1 && $words <= 6 && ! $this->endsWithSentencePunctuation($line);
    }

    /**
     * A long sentence is instruction/legal boilerplate, not a field label.
     */
    private function isLongSentence(string $text): bool
    {
        return str_word_count($text) > 12 && $this->endsWithSentencePunctuation($text);
    }

    private function endsWithSentencePunctuation(string $text): bool
    {
        return (bool) preg_match('/[.?!]$/', trim($text));
    }

    private function looksLikeTitle(string $line): bool
    {
        if (str_contains($line, ':') || preg_match('/_{2,}/', $line)) {
            return false;
        }

        // ALL CAPS heading, or a Title-Case heading longer than two words.
        $isAllCaps = $line === mb_strtoupper($line) && preg_match('/[A-Z]/', $line);
        $isHeading = str_word_count($line) >= 2 && ! $this->endsWithSentencePunctuation($line);

        return (bool) ($isAllCaps || $isHeading);
    }

    private function isExcluded(string $text): bool
    {
        return (bool) preg_match(self::SIGNATURE_PATTERN, $text) || $this->isLongSentence($text);
    }

    private function isOptional(string $label): bool
    {
        $haystack = strtolower($label);

        foreach (self::OPTIONAL_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Append a field, generating a unique snake_case key and sequential order.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, int>  $seenKeys
     */
    private function push(array &$fields, array &$seenKeys, int &$order, string $label): void
    {
        $baseKey = FormTemplateHelper::normalizeFieldKey($label);
        $key = $baseKey;

        if (isset($seenKeys[$baseKey])) {
            $seenKeys[$baseKey]++;
            $key = $baseKey.'_'.$seenKeys[$baseKey];
        } else {
            $seenKeys[$baseKey] = 1;
        }

        $order++;

        $fields[] = [
            'label' => $label,
            'field_key' => $key,
            'field_type' => $this->inferType($label),
            'is_required' => ! $this->isOptional($label),
            'field_order' => $order,
        ];
    }
}
