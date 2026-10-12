<?php

namespace App\Forms;

use App\Support\ImageMetadata;
use Illuminate\Process\Exceptions\ProcessTimedOutException as MetadataProcessTimedOutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

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
     * Whether a lone (option-less) checkbox was ticked. Tolerates the historic
     * shapes ("1"/"on"/"yes"/true) as well as the normalized 1/0.
     *
     * @param  array<string,mixed>  $payload
     */
    public static function isChecked(array $payload, string $key): bool
    {
        return self::truthy($payload[$key] ?? null);
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
            case FieldType::SEARCH:
            case FieldType::RADIO:
                if (is_array($value)) {
                    return implode(', ', self::optionLabels($value, $options));
                }

                return self::optionLabel((string) $value, $options);

            default:
                if (is_array($value)) {
                    return implode(', ', array_map('strval', $value));
                }

                return (string) $value;
        }
    }

    /**
     * Display labels for a multi-select's chosen values (blanks dropped).
     *
     * @param  array<int|string,mixed>  $values
     * @param  array<string,mixed>  $options
     * @return array<int,string>
     */
    public static function optionLabels(array $values, array $options): array
    {
        $labels = [];
        foreach ($values as $value) {
            $value = trim((string) (is_scalar($value) ? $value : ''));
            if ($value !== '') {
                $labels[] = self::optionLabel($value, $options);
            }
        }

        return $labels;
    }

    /**
     * Map a stored option value to its display label, if known.
     *
     * @param  array<string,mixed>  $options
     */
    public static function optionLabel(string $value, array $options): string
    {
        $pairs = FieldType::optionPairs($options);

        // A sourced select/search stores an id: resolve the source (unscoped —
        // this is admin-facing display) so the printed/on-screen value shows the
        // entry's label rather than the raw id.
        if ($pairs === [] && ($source = OptionSource::forField($options)) !== null) {
            $pairs = OptionSource::options($source, null, scoped: false);
        }

        foreach ($pairs as $pair) {
            if ($pair['value'] === $value) {
                return $pair['label'];
            }
        }

        return $value;
    }

    /**
     * Field types whose value is a stored file (or an inline capture of one)
     * rather than text: everything an admin reviewing a submission has to be
     * able to open at full size.
     */
    public static function holdsAttachments(string $type): bool
    {
        return FieldType::isFileLike($type) || in_array($type, [
            FieldType::SIGNATURE, FieldType::WAIVER_SCAN, FieldType::MULTI_IMAGE,
        ], true);
    }

    /**
     * Every viewable attachment behind a field's value, normalized so one
     * renderer handles them all: single uploads, photo sets, captured
     * signatures (which may still be inline data-URLs) and scanned waivers.
     *
     * A value that is neither an inline image nor a file present on the disk is
     * left out — the caller falls back to printing it as text.
     *
     * @param  array<string,mixed>  $payload
     * @return array<int,array{url:string, name:string, is_image:bool, is_video:bool, size:?int, path:?string, metadata:array<string,string>}>
     */
    public static function attachments(array $payload, string $key, string $type, ?string $disk = null): array
    {
        if (! self::holdsAttachments($type)) {
            return [];
        }

        return self::attachmentsFor($payload[$key] ?? null, $disk);
    }

    /**
     * Attachments carried by payload keys that no field owns — the ID-scan
     * wizard's `id_photo_front` / `id_photo_back` captures ride along beside the
     * fields (see FormRenderController::submit), so a fields-only render leaves
     * the admin unable to see the ID the applicant photographed.
     *
     * @param  array<string,mixed>  $payload
     * @param  array<int,string>  $fieldKeys  keys the field loop already renders
     * @return array<string,array<int,array{url:string, name:string, is_image:bool, is_video:bool, size:?int, path:?string, metadata:array<string,string> }>> label => attachments
     */
    public static function orphanAttachments(array $payload, array $fieldKeys, ?string $disk = null): array
    {
        $orphans = [];

        foreach ($payload as $key => $value) {
            if (in_array((string) $key, $fieldKeys, true)) {
                continue;
            }

            // Only stored files qualify: an inline data-URL under an unowned key
            // has no label to explain it, and every scalar is already text.
            $found = array_values(array_filter(
                self::attachmentsFor($value, $disk),
                static fn (array $item) => $item['path'] !== null,
            ));

            if ($found !== []) {
                $orphans[Str::headline((string) $key)] = $found;
            }
        }

        return $orphans;
    }

    /**
     * @return array<int,array{url:string, name:string, is_image:bool, is_video:bool, size:?int, path:?string, metadata:array<string,string>}>
     */
    private static function attachmentsFor(mixed $value, ?string $disk = null): array
    {
        $disk ??= (string) config('documents.disk', 'public');

        if ($value === null || $value === '' || is_bool($value)) {
            return [];
        }

        $items = [];
        foreach ((array) $value as $item) {
            if (! is_string($item) || $item === '') {
                continue;
            }

            if (str_starts_with($item, 'data:')) {
                $isImage = str_starts_with($item, 'data:image');
                $items[] = [
                    'url' => $item,
                    'name' => 'Captured image',
                    'is_image' => $isImage,
                    'is_video' => false,
                    'size' => null,
                    'path' => null,
                    'metadata' => [],
                ];

                continue;
            }

            // A path is only an attachment if the file is really there; this is
            // also what keeps ordinary text values out of the gallery.
            if (! Storage::disk($disk)->exists($item)) {
                continue;
            }

            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $isImage = in_array($extension, ['jpe', 'jpeg', 'jpg', 'png', 'gif', 'webp', 'heic', 'heif', 'bmp', 'svg'], true);
            $isVideo = in_array($extension, ['mp4', 'mov', 'm4v', 'webm', 'avi', 'mkv', '3gp', 'mpeg', 'mpg'], true);

            $metadata = [];
            $metadataError = null;
            try {
                $metadata = ($isImage || $isVideo) ? self::mediaMetadata($item, $disk, $extension) : [];
            } catch (\RuntimeException|\JsonException|MetadataProcessTimedOutException $exception) {
                Log::warning('Unable to extract submitted attachment metadata.', [
                    'path' => $item,
                    'error' => $exception->getMessage(),
                ]);
                $metadataError = 'EXIF data unavailable';
            }

            $items[] = [
                'url' => Storage::disk($disk)->url($item),
                'name' => basename($item),
                'is_image' => $isImage,
                'is_video' => $isVideo,
                'size' => Storage::disk($disk)->size($item),
                'path' => $item,
                'metadata' => $metadata,
                'metadata_error' => $metadataError,
            ];
        }

        return $items;
    }

    /**
     * Extract displayable photo EXIF or video container metadata.
     *
     * @return array<string,string>
     */
    private static function mediaMetadata(string $path, string $disk, string $extension): array
    {
        $absolutePath = Storage::disk($disk)->path($path);

        if (in_array($extension, ['jpe', 'jpeg', 'jpg', 'png', 'gif', 'webp', 'heic', 'heif', 'bmp', 'svg'], true)) {
            $metadata = [];
            self::flattenMetadata(ImageMetadata::read($absolutePath), 'EXIF', $metadata);

            return $metadata;
        }

        if (in_array($extension, ['mp4', 'mov', 'm4v', 'webm', 'avi', 'mkv', '3gp', 'mpeg', 'mpg'], true)) {
            return self::videoMetadata($absolutePath);
        }

        return [];
    }

    /**
     * @param  array<mixed>  $values
     * @param  array<string,string>  $metadata
     */
    private static function flattenMetadata(array $values, string $prefix, array &$metadata): void
    {
        foreach ($values as $key => $value) {
            if (count($metadata) >= 100) {
                return;
            }

            $label = $prefix.'.'.(string) $key;
            if (is_array($value)) {
                self::flattenMetadata($value, $label, $metadata);
            } elseif (is_scalar($value) && trim((string) $value) !== '') {
                $metadata[$label] = Str::limit((string) $value, 500, '…');
            }
        }
    }

    /**
     * @return array<string,string>
     */
    private static function videoMetadata(string $absolutePath): array
    {
        $ffprobe = (new ExecutableFinder)->find('ffprobe');
        if ($ffprobe === null) {
            return [];
        }

        $process = new Process([
            $ffprobe,
            '-v', 'error',
            '-show_format',
            '-show_streams',
            '-of', 'json',
            $absolutePath,
        ]);
        $process->setTimeout(5);
        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            Log::warning('Timed out reading video metadata for a submitted attachment.', [
                'extension' => pathinfo($absolutePath, PATHINFO_EXTENSION),
            ]);

            return [];
        }

        if (! $process->isSuccessful()) {
            Log::warning('Unable to read video metadata for a submitted attachment.', [
                'extension' => pathinfo($absolutePath, PATHINFO_EXTENSION),
                'error' => Str::limit(trim($process->getErrorOutput()), 300),
            ]);

            return [];
        }

        $details = json_decode($process->getOutput(), true);
        if (! is_array($details)) {
            Log::warning('Video metadata reader returned invalid JSON.', [
                'extension' => pathinfo($absolutePath, PATHINFO_EXTENSION),
            ]);

            return [];
        }

        $metadata = [];
        foreach (($details['format'] ?? []) as $key => $value) {
            if (count($metadata) >= 100) {
                break;
            }

            if ($key === 'tags' && is_array($value)) {
                self::flattenMetadata($value, 'Format', $metadata);
            } elseif (is_scalar($value) && trim((string) $value) !== '') {
                $metadata['Format.'.(string) $key] = Str::limit((string) $value, 500, '…');
            }
        }

        foreach (($details['streams'] ?? []) as $index => $stream) {
            if (! is_array($stream)) {
                continue;
            }
            $prefix = 'Stream '.((int) $index + 1);
            foreach ($stream as $key => $value) {
                if (count($metadata) >= 100) {
                    break 2;
                }

                if ($key === 'tags' && is_array($value)) {
                    self::flattenMetadata($value, $prefix.'.Tags', $metadata);
                } elseif (is_scalar($value) && trim((string) $value) !== '') {
                    $metadata[$prefix.'.'.(string) $key] = Str::limit((string) $value, 500, '…');
                }
            }
        }

        return $metadata;
    }

    /** A file size an admin can read at a glance. */
    public static function humanSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $value = $bytes / 1024;
        foreach (['KB', 'MB', 'GB'] as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return round($value, 1).' '.$unit;
            }
            $value /= 1024;
        }

        return $bytes.' B';
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
