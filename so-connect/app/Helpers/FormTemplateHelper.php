<?php

namespace App\Helpers;

use App\Models\Template\TemplateDescription;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class FormTemplateHelper
{
    public const ACTION_TYPE_DOCUMENT_GENERATION = 3;

    public const ACTION_TYPE_DOCUMENT_ACCESS = 4;

    public const ACTION_TYPE_FORM_UPLOAD = 8;

    public static function normalizeFieldKey(string $fieldKey): string
    {
        $normalized = Str::of($fieldKey)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/i', '_')
            ->trim('_')
            ->toString();

        return $normalized !== '' ? $normalized : 'field';
    }

    public static function placeholderBaseKey(string $fieldKey): string
    {
        $normalized = self::normalizeFieldKey($fieldKey);
        $base = preg_replace('/\d+$/', '', $normalized) ?? $normalized;

        // Trim any trailing underscores left after stripping the numeric suffix
        // (e.g. "item_1" → base "item_" → trimmed to "item").
        $base = rtrim($base, '_');

        return $base !== '' ? $base : $normalized;
    }

    public static function placeholderIndex(string $fieldKey): ?int
    {
        $normalized = self::normalizeFieldKey($fieldKey);

        if (! preg_match('/(\d+)$/', $normalized, $matches)) {
            return null;
        }

        $index = (int) ($matches[1] ?? 0);

        return $index > 0 ? $index : null;
    }

    public static function wrapIndexedPlaceholder(string $fieldKey): string
    {
        return '{{'.self::placeholderBaseKey($fieldKey).'#}}';
    }

    public static function normalizePlaceholder(string $placeholder): string
    {
        $trimmed = trim($placeholder);

        if (str_starts_with($trimmed, '{{') && str_ends_with($trimmed, '}}')) {
            $trimmed = trim(substr($trimmed, 2, -2));
        }

        return self::normalizeFieldKey($trimmed);
    }

    public static function wrapPlaceholder(string $fieldKey): string
    {
        return '{{'.self::normalizeFieldKey($fieldKey).'}}';
    }

    /**
     * @return array<int, string>
     */
    public static function extractPlaceholdersFromDocx(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException('DOCX template file does not exist: '.$absolutePath);
        }

        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException('Unable to read DOCX template file.');
        }

        $combined = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if (! is_string($entryName)) {
                continue;
            }

            // Only read body content — skip styles, settings, fontTable, etc.
            // which never contain user placeholders but add noise after tag-stripping.
            $isDocumentBody = $entryName === 'word/document.xml';
            $isHeaderOrFooter = (bool) preg_match('/^word\/(header|footer)\d*\.xml$/', $entryName);

            if (! $isDocumentBody && ! $isHeaderOrFooter) {
                continue;
            }

            $content = $zip->getFromIndex($i);

            if (! is_string($content) || $content === '') {
                continue;
            }

            // Join <w:t> text nodes per paragraph so that placeholders split
            // across XML runs (common in Word-generated DOCX) are reconstructed.
            $combined .= "\n".self::joinTextRunsPerParagraph($content);
        }

        $zip->close();

        if ($combined === '') {
            return [];
        }

        // Allow trailing # which marks a field as multiline (e.g. {{members#}}).
        preg_match_all('/\{\{\s*([a-zA-Z0-9_\.:-]+#?)\s*\}\}/', $combined, $matches);

        return collect($matches[1] ?? [])
            ->map(function (string $placeholder): string {
                // Preserve the trailing # but normalise everything before it.
                $isMultiline = str_ends_with($placeholder, '#');
                $base = $isMultiline ? substr($placeholder, 0, -1) : $placeholder;
                $normalizedBase = self::normalizeFieldKey($base);

                return $normalizedBase !== '' ? ($normalizedBase.($isMultiline ? '#' : '')) : '';
            })
            ->filter(fn (string $placeholder) => $placeholder !== '' && $placeholder !== '#')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Extracts text from DOCX XML by concatenating all <w:t> nodes within each
     * paragraph before joining paragraphs with newlines. This correctly handles
     * Word's habit of splitting placeholder text (e.g. {{item1}}) across multiple
     * XML runs inside the same paragraph.
     */
    private static function joinTextRunsPerParagraph(string $xml): string
    {
        // Split on paragraph-close tags so each chunk is one paragraph.
        $paragraphChunks = preg_split('/<\/w:p\s*>/', $xml) ?? [];

        $lines = [];

        foreach ($paragraphChunks as $chunk) {
            // Collect every <w:t ...>...</w:t> segment within this paragraph.
            preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/', $chunk, $textMatches);

            if (! empty($textMatches[1])) {
                // Concatenate runs directly — no separator — so {{item}} split
                // as "{{" + "item" + "}}" across runs becomes "{{item}}".
                $paraText = implode('', $textMatches[1]);
                // Decode XML character entities (e.g. &amp; &lt;) so the
                // placeholder regex sees literal characters.
                $paraText = html_entity_decode($paraText, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $lines[] = $paraText;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, TemplateDescription>|array<int, TemplateDescription>  $mappings
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function buildReplacementMap(Collection|array $mappings, array $payload): array
    {
        $mappingCollection = $mappings instanceof Collection
            ? $mappings
            : collect($mappings);

        $normalizedPayload = collect($payload)
            ->mapWithKeys(function ($value, $key) {
                return [self::normalizeFieldKey((string) $key) => self::normalizePayloadValue($value)];
            })
            ->all();

        $multipleInputIndexes = [];

        return $mappingCollection
            ->mapWithKeys(function (TemplateDescription $mapping) use ($normalizedPayload, &$multipleInputIndexes) {
                $rawPlaceholderKey = (string) ($mapping->placeholder_key ?: $mapping->field_key);
                $fieldKey = self::normalizeFieldKey((string) ($mapping->field_key ?: $mapping->placeholder_key));
                $placeholderKey = self::normalizeFieldKey($rawPlaceholderKey);
                // Preserve the # suffix for multiline detection even though normalizeFieldKey strips it.
                $effectivePlaceholderKey = str_ends_with($rawPlaceholderKey, '#') ? $placeholderKey.'#' : $placeholderKey;

                $hasPayloadValue = array_key_exists($fieldKey, $normalizedPayload);
                $payloadValue = $hasPayloadValue ? $normalizedPayload[$fieldKey] : '';

                if (! $hasPayloadValue && self::placeholderIndex($placeholderKey) !== null) {
                    $baseFieldKey = self::placeholderBaseKey($fieldKey);
                    if ($baseFieldKey !== $fieldKey && array_key_exists($baseFieldKey, $normalizedPayload)) {
                        $fieldKey = $baseFieldKey;
                        $payloadValue = $normalizedPayload[$fieldKey];
                        $hasPayloadValue = true;
                    }
                }

                if (is_array($payloadValue)) {
                    return [$placeholderKey => self::multipleInputValueForPlaceholder($payloadValue, $effectivePlaceholderKey, $fieldKey, $multipleInputIndexes)];
                }

                return [$placeholderKey => self::stringifyValue($payloadValue)];
            })
            ->all();
    }

    /**
     * @param  Collection<int, TemplateDescription>|array<int, TemplateDescription>  $mappings
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public static function missingRequiredFields(Collection|array $mappings, array $payload): array
    {
        $mappingCollection = $mappings instanceof Collection
            ? $mappings
            : collect($mappings);

        $normalizedPayload = collect($payload)
            ->mapWithKeys(fn ($value, $key) => [self::normalizeFieldKey((string) $key) => self::normalizePayloadValue($value)])
            ->all();

        $multipleInputIndexes = [];

        return $mappingCollection
            ->filter(function (TemplateDescription $mapping) use ($normalizedPayload, &$multipleInputIndexes) {
                if (! (bool) $mapping->is_required) {
                    return false;
                }

                $rawPlaceholderKey = (string) ($mapping->placeholder_key ?: $mapping->field_key);
                $fieldKey = self::normalizeFieldKey((string) ($mapping->field_key ?: $mapping->placeholder_key));
                $placeholderKey = self::normalizeFieldKey($rawPlaceholderKey);
                $effectivePlaceholderKey = str_ends_with($rawPlaceholderKey, '#') ? $placeholderKey.'#' : $placeholderKey;
                $payloadValue = $normalizedPayload[$fieldKey] ?? '';

                if (is_array($payloadValue)) {
                    $value = self::multipleInputValueForPlaceholder($payloadValue, $effectivePlaceholderKey, $fieldKey, $multipleInputIndexes);

                    return trim($value) === '';
                }

                return trim((string) self::stringifyValue($payloadValue)) === '';
            })
            ->map(fn (TemplateDescription $mapping) => self::normalizeFieldKey((string) ($mapping->placeholder_key ?: $mapping->field_key)))
            ->unique()
            ->values()
            ->all();
    }

    public static function encodeDocumentGenerationAction(
        int $organizationId,
        int $submissionId,
        int $formId,
        int $requesterUserId,
    ): string {
        return implode('|', [
            max(0, $organizationId),
            max(0, $submissionId),
            max(0, $formId),
            max(0, $requesterUserId),
        ]);
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    public static function decodeDocumentGenerationAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 4) {
            return [0, 0, 0, 0];
        }

        return [
            self::toPositiveInt($parts[0]),
            self::toPositiveInt($parts[1]),
            self::toPositiveInt($parts[2]),
            self::toPositiveInt($parts[3]),
        ];
    }

    public static function encodeDocumentAccessAction(int $organizationId, int $generatedDocumentId, int $requesterUserId): string
    {
        return implode('|', [
            max(0, $organizationId),
            max(0, $generatedDocumentId),
            max(0, $requesterUserId),
        ]);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    public static function decodeDocumentAccessAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 3) {
            return [0, 0, 0];
        }

        return [
            self::toPositiveInt($parts[0]),
            self::toPositiveInt($parts[1]),
            self::toPositiveInt($parts[2]),
        ];
    }

    public static function encodeFormUploadAction(int $organizationId, int $formId, int $templateId, int $uploaderUserId): string
    {
        return implode('|', [
            max(0, $organizationId),
            max(0, $formId),
            max(0, $templateId),
            max(0, $uploaderUserId),
        ]);
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    public static function decodeFormUploadAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 4) {
            return [0, 0, 0, 0];
        }

        return [
            self::toPositiveInt($parts[0]),
            self::toPositiveInt($parts[1]),
            self::toPositiveInt($parts[2]),
            self::toPositiveInt($parts[3]),
        ];
    }

    private static function toPositiveInt(string $value): int
    {
        return ctype_digit($value) ? (int) $value : 0;
    }

    /**
     * @param  mixed  $value
     */
    private static function stringifyValue($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => (string) $item, $value));
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>|string
     */
    private static function normalizePayloadValue($value): array|string
    {
        if (is_array($value)) {
            $flat = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $flat[] = trim((string) $item);
                }
            }
            return array_values($flat);
        }

        return trim((string) $value);
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<string, int>  $multipleInputIndexes
     */
    private static function multipleInputValueForPlaceholder(array $values, string $placeholderKey, string $fieldKey, array &$multipleInputIndexes): string
    {
        // {{fieldname#}} format: join every non-empty row with a newline.
        if (str_ends_with($placeholderKey, '#')) {
            $rows = array_values(array_filter(array_map('trim', $values), fn ($v) => $v !== ''));

            return implode("\n", $rows);
        }

        // Legacy numbered format {{field1}}, {{field2}}, … — pick by slot index.
        $placeholderIndex = self::placeholderIndex($placeholderKey);

        if ($placeholderIndex !== null) {
            return (string) ($values[$placeholderIndex - 1] ?? '');
        }

        $currentIndex = $multipleInputIndexes[$fieldKey] ?? 0;
        $multipleInputIndexes[$fieldKey] = $currentIndex + 1;

        return (string) ($values[$currentIndex] ?? '');
    }
}
