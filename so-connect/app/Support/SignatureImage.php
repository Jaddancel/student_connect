<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared handling for captured signatures (base64 PNG data-URLs drawn on the
 * signature pad). One place decodes and stores them so every capture surface —
 * form fields, the profile page, ID-scan imports — persists identically.
 */
final class SignatureImage
{
    /**
     * Decode a data-URL and store it as a PNG under $dir on the documents disk.
     * Returns the disk-relative path, or null when the value is not a decodable
     * image data-URL (nothing drawn, or a tampered payload).
     */
    public static function storeDataUrl(?string $dataUrl, string $dir): ?string
    {
        $binary = self::decodeDataUrl($dataUrl);
        if ($binary === null) {
            return null;
        }

        $path = trim($dir, '/').'/'.Str::random(20).'.png';
        Storage::disk(self::disk())->put($path, $binary);

        return $path;
    }

    /**
     * The raw PNG bytes of a signature data-URL, or null when undecodable.
     */
    public static function decodeDataUrl(?string $dataUrl): ?string
    {
        if (! is_string($dataUrl) || ! str_starts_with($dataUrl, 'data:image')) {
            return null;
        }

        $parts = explode(',', $dataUrl, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $binary = base64_decode($parts[1], true);

        return $binary === false ? null : $binary;
    }

    public static function disk(): string
    {
        return (string) config('documents.disk', 'public');
    }
}
