<?php

namespace App\Forms;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a stored {@see \App\Models\FormSubmission} payload into display-ready
 * values for the on-screen renderer and the HTML→PDF document view.
 *
 * The payload is keyed by `field_key` (the compatibility contract), so every
 * helper here looks values up by key.
 */
final class SubmissionPresenter
{
    /**
     * Raw value for a field key out of the payload (may be string/array/null).
     *
     * @param  array<string,mixed>  $payload
     */
    public static function raw(array $payload, string $key): mixed
    {
        return $payload[$key] ?? null;
    }

    /**
     * A human-readable scalar rendering of a field's value.
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $options  the field's `field_options`
     */
    public static function display(array $payload, string $key, string $type, array $options = []): string
    {
        $value = $payload[$key] ?? null;

        if ($value === null || $value === '') {
            return '';
        }

        switch ($type) {
            case FieldType::DATE:
                try {
                    return Carbon::parse((string) $value)->format('F j, Y');
                } catch (\Throwable) {
                    return (string) $value;
                }

            case FieldType::CHECKBOX:
                if (is_array($value)) {
                    return implode(', ', array_map('strval', $value));
                }
                // A lone checkbox stores a truthy scalar.
                return self::truthy($value) ? 'Yes' : 'No';

            case FieldType::SELECT:
            case FieldType::RADIO:
                return self::optionLabel((string) $value, $options);

            default:
                if (is_array($value)) {
                    return implode(', ', array_map('strval', $value));
                }
                return (string) $value;
        }
    }

    /**
     * Map a stored option value to its display label, if known.
     *
     * @param  array<string,mixed>  $options
     */
    public static function optionLabel(string $value, array $options): string
    {
        foreach (FieldType::optionPairs($options) as $pair) {
            if ($pair['value'] === $value) {
                return $pair['label'];
            }
        }

        return $value;
    }

    /**
     * Resolve a field's value(s) into one or more displayable image data-URIs.
     *
     * Handles uploaded image/file paths (string or array) and inline signature
     * data-URLs. Returns absolute `data:` URIs so dompdf can embed them without
     * needing remote/file access enabled.
     *
     * @param  array<string,mixed>  $payload
     * @return string[]
     */
    public static function imageDataUris(array $payload, string $key, ?string $disk = null): array
    {
        $disk ??= (string) config('documents.disk', 'public');
        $value = $payload[$key] ?? null;

        if ($value === null || $value === '') {
            return [];
        }

        $uris = [];
        foreach ((array) $value as $item) {
            $item = (string) $item;
            if ($item === '') {
                continue;
            }
            // Already an inline data-URL (e.g. a captured signature).
            if (str_starts_with($item, 'data:')) {
                $uris[] = $item;
                continue;
            }
            $uri = self::pathToDataUri($item, $disk);
            if ($uri !== null) {
                $uris[] = $uri;
            }
        }

        return $uris;
    }

    /**
     * Convert a stored disk-relative path to a base64 `data:` URI, or null if
     * the file is missing / not an image.
     */
    public static function pathToDataUri(string $relativePath, string $disk): ?string
    {
        if (! Storage::disk($disk)->exists($relativePath)) {
            return null;
        }

        $absolute = Storage::disk($disk)->path($relativePath);
        $info = @getimagesize($absolute);
        if ($info === false) {
            return null;
        }

        $contents = @file_get_contents($absolute);
        if ($contents === false) {
            return null;
        }

        return 'data:'.$info['mime'].';base64,'.base64_encode($contents);
    }

    private static function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }
}
