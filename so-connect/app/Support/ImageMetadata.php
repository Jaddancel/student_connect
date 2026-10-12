<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class ImageMetadata
{
    /** @return array<string, scalar|array|null> */
    public static function read(string $absolutePath): array
    {
        $result = Process::timeout(10)->run([
            'exiftool', '-json', '-G1', '-s', '-EXIF:all', '-XMP:all', '-IPTC:all', $absolutePath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('Unable to read image metadata: '.trim($result->errorOutput()));
        }

        $records = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($records) || ! isset($records[0]) || ! is_array($records[0])) {
            throw new RuntimeException('Image metadata reader returned an unexpected result.');
        }

        $metadata = $records[0];
        unset($metadata['SourceFile']);

        return $metadata;
    }
}
