<?php

namespace App\Services;

use App\Models\SignatureRecord;
use Illuminate\Support\Facades\Storage;

class SignatureRecordService
{
    private const MATCH_THRESHOLD = 10;

    public function store(
        string $submitterName,
        string $signaturePath,
        ?int $submissionId = null,
        ?int $userId = null,
    ): SignatureRecord {
        $disk = config('filesystems.default', 'public');
        $absolutePath = Storage::disk($disk)->path($signaturePath);

        return SignatureRecord::create([
            'submitter_name' => $submitterName,
            'user_id' => $userId,
            'form_submission_id' => $submissionId,
            'signature_path' => $signaturePath,
            'perceptual_hash' => $this->dHash($absolutePath),
        ]);
    }

    /**
     * Compare a signature against all stored records for the given name.
     *
     * @return array{previous_count: int, status: string, previous_signatures: array}
     */
    public function compare(string $currentSignaturePath, string $submitterName): array
    {
        $previous = SignatureRecord::where('submitter_name', $submitterName)->get();

        if ($previous->isEmpty()) {
            return [
                'previous_count' => 0,
                'status' => 'unknown',
                'previous_signatures' => [],
            ];
        }

        $disk = config('filesystems.default', 'public');
        $absolutePath = Storage::disk($disk)->path($currentSignaturePath);
        $currentHash = $this->dHash($absolutePath);

        $bestDistance = PHP_INT_MAX;
        foreach ($previous as $record) {
            $distance = $this->hammingDistance($currentHash, $record->perceptual_hash);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
            }
        }

        $status = $bestDistance <= self::MATCH_THRESHOLD ? 'match' : 'different';

        return [
            'previous_count' => $previous->count(),
            'status' => $status,
            'previous_signatures' => $previous->pluck('signature_path')->all(),
        ];
    }

    /**
     * Compute a 64-bit difference hash of an image using GD.
     * Resize to 9×8 grayscale, compare adjacent pixels left-to-right per row.
     */
    private function dHash(string $imagePath): string
    {
        if (! is_file($imagePath)) {
            return str_repeat('0', 16);
        }

        try {
            $src = @imagecreatefromstring(file_get_contents($imagePath));
            if (! $src) {
                return str_repeat('0', 16);
            }

            $resized = imagecreatetruecolor(9, 8);
            imagecopyresampled($resized, $src, 0, 0, 0, 0, 9, 8, imagesx($src), imagesy($src));
            imagedestroy($src);

            $bits = '';
            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    $left = imagecolorat($resized, $x, $y);
                    $right = imagecolorat($resized, $x + 1, $y);
                    $gLeft = $this->luminance($left);
                    $gRight = $this->luminance($right);
                    $bits .= $gLeft > $gRight ? '1' : '0';
                }
            }
            imagedestroy($resized);

            return base_convert(ltrim(sprintf('%064s', $bits) ?: '0', '0') ?: '0', 2, 16);
        } catch (\Throwable) {
            return str_repeat('0', 16);
        }
    }

    private function luminance(int $color): float
    {
        $r = ($color >> 16) & 0xFF;
        $g = ($color >> 8) & 0xFF;
        $b = $color & 0xFF;

        return 0.299 * $r + 0.587 * $g + 0.114 * $b;
    }

    private function hammingDistance(string $hash1, string $hash2): int
    {
        $xor = hexdec($hash1) ^ hexdec($hash2);
        $distance = 0;
        while ($xor) {
            $distance += $xor & 1;
            $xor >>= 1;
        }

        return $distance;
    }
}
