<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Session-scoped holding pen for the ID-scan wizard's captured front/back
 * photos, so a validation failure on some *other* field of the sign-up form
 * doesn't force the user to re-scan their ID. A browser never resubmits
 * `<input type=file>` contents across a redirect — without this, the photo
 * the wizard already scanned is silently dropped the moment any other field
 * fails validation, and the resubmitted form saves a null ID photo.
 *
 * (The scanned signature and every plain text field the wizard autofilled
 * don't need this: they ride as normal form values, which Laravel's
 * `withInput()` already restores via `old()` across the same redirect.)
 *
 * Keyed by session id only (one slot per browser session) — deliberately not
 * namespaced per form, since only one ID_SCAN-bearing form (Sign Up) exists
 * today; revisit if a second one needs to coexist in the same session.
 */
final class IdScanRetryCache
{
    private const DISK = 'local';

    private const ROOT = 'id-scan-retry';

    /** Stale captures older than this are swept on the next write. */
    private const TTL_HOURS = 6;

    /**
     * Record a side's scanned photo and the fields detected from it,
     * replacing whatever this side previously held.
     *
     * @param  array<string,mixed>  $fields
     */
    public static function remember(string $sessionId, string $side, UploadedFile $photo, array $fields): void
    {
        $disk = self::disk();
        $dir = self::dir($sessionId);

        $bytes = @file_get_contents($photo->getRealPath());
        if ($bytes === false) {
            return;
        }
        $disk->put("{$dir}/{$side}.jpg", $bytes);

        $meta = self::readMeta($sessionId);
        $meta[$side] = ['fields' => $fields, 'updated_at' => now()->toIso8601String()];
        $disk->put("{$dir}/meta.json", json_encode($meta));

        self::gc($disk);
    }

    /**
     * What's cached for this session: `front`/`back` => ['fields' => [...]],
     * only for sides that actually have a stored photo.
     *
     * @return array<string,array{fields:array<string,mixed>}>
     */
    public static function summary(string $sessionId): array
    {
        $meta = self::readMeta($sessionId);

        return array_filter(
            array_intersect_key($meta, array_flip(['front', 'back'])),
            fn (string $side) => self::photoPath($sessionId, $side) !== null,
            ARRAY_FILTER_USE_KEY,
        );
    }

    public static function photoPath(string $sessionId, string $side): ?string
    {
        $path = self::dir($sessionId)."/{$side}.jpg";

        return self::disk()->exists($path) ? $path : null;
    }

    /**
     * Move a cached side's photo bytes into a form's upload storage, exactly
     * as `$request->file($key)->store($dir, $disk)` would. Returns the
     * disk-relative stored path, or null when nothing is cached for this side.
     */
    public static function storeInto(string $sessionId, string $side, string $disk, string $dir): ?string
    {
        $cachedPath = self::photoPath($sessionId, $side);
        if ($cachedPath === null) {
            return null;
        }

        $bytes = self::disk()->get($cachedPath);
        $path = trim($dir, '/').'/'.Str::random(40).'.jpg';
        Storage::disk($disk)->put($path, $bytes);

        return $path;
    }

    public static function forget(string $sessionId): void
    {
        self::disk()->deleteDirectory(self::dir($sessionId));
    }

    private static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(self::DISK);
    }

    private static function dir(string $sessionId): string
    {
        // The session id is a random alphanumeric token (never user input),
        // safe to use as a path segment as-is.
        return self::ROOT.'/'.$sessionId;
    }

    /**
     * @return array<string,array{fields:array<string,mixed>,updated_at:string}>
     */
    private static function readMeta(string $sessionId): array
    {
        $path = self::dir($sessionId).'/meta.json';
        $disk = self::disk();
        if (! $disk->exists($path)) {
            return [];
        }
        $decoded = json_decode((string) $disk->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Best-effort cleanup of stale session captures, run opportunistically on write. */
    private static function gc(\Illuminate\Contracts\Filesystem\Filesystem $disk): void
    {
        if (! $disk->exists(self::ROOT)) {
            return;
        }
        $cutoff = now()->subHours(self::TTL_HOURS)->timestamp;
        foreach ($disk->directories(self::ROOT) as $dir) {
            $metaPath = "{$dir}/meta.json";
            $modified = $disk->exists($metaPath) ? $disk->lastModified($metaPath) : 0;
            if ($modified < $cutoff) {
                $disk->deleteDirectory($dir);
            }
        }
    }
}
