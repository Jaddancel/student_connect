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
        $opened = $zip->open($absolutePath);

        if ($opened !== true) {
            throw new RuntimeException('Unable to read DOCX template file.');
        }

        $xmlPayload = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if (! is_string($entryName)) {
                continue;
            }

            if (! str_starts_with($entryName, 'word/')) {
                continue;
            }

            if (! str_ends_with($entryName, '.xml')) {
                continue;
            }

            $content = $zip->getFromIndex($i);

            if (! is_string($content) || $content === '') {
                continue;
            }

            // Flatten Word text runs so placeholder regex can match markers split by tags.
            $flattened = preg_replace('/<[^>]+>/', '', $content) ?? '';

            if ($flattened === '') {
                continue;
            }

            $xmlPayload .= "\n".$flattened;
        }

        $zip->close();

        if ($xmlPayload === '') {
            return [];
        }

        preg_match_all('/\{\{\s*([a-zA-Z0-9_\.:-]+)\s*\}\}/', $xmlPayload, $matches);

        $placeholders = collect($matches[1] ?? [])
            ->map(fn ($placeholder) => self::normalizeFieldKey((string) $placeholder))
            ->filter(fn (string $placeholder) => $placeholder !== '')
            ->unique()
            ->values()
            ->all();

        return $placeholders;
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
                return [self::normalizeFieldKey((string) $key) => self::stringifyValue($value)];
            })
            ->all();

        return $mappingCollection
            ->mapWithKeys(function (TemplateDescription $mapping) use ($normalizedPayload) {
                $fieldKey = self::normalizeFieldKey((string) ($mapping->field_key ?: $mapping->placeholder_key));

                return [$fieldKey => (string) ($normalizedPayload[$fieldKey] ?? '')];
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
            ->mapWithKeys(fn ($value, $key) => [self::normalizeFieldKey((string) $key) => self::stringifyValue($value)])
            ->all();

        return $mappingCollection
            ->filter(function (TemplateDescription $mapping) use ($normalizedPayload) {
                if (! (bool) $mapping->is_required) {
                    return false;
                }

                $fieldKey = self::normalizeFieldKey((string) ($mapping->field_key ?: $mapping->placeholder_key));

                return trim((string) ($normalizedPayload[$fieldKey] ?? '')) === '';
            })
            ->map(fn (TemplateDescription $mapping) => self::normalizeFieldKey((string) ($mapping->field_key ?: $mapping->placeholder_key)))
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
}
