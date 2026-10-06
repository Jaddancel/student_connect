<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared handling for captured signatures (pad drawings, photographed paper, ID
 * zone crops). One place decodes, EXTRACTS THE INK, and stores them so every
 * capture surface persists the signature itself — never the photo it came from.
 *
 * Extraction is deliberately server-side and authoritative: the browser also
 * previews an extraction, but whatever it posts is re-extracted here, so no
 * surface can store a whole photograph by bypassing (or failing) the client.
 */
final class SignatureImage
{
    /** Longest edge of a stored signature; photos are downscaled to this first. */
    private const MAX_EDGE = 1200;

    /** Fewer ink pixels than this means nothing legible was captured. */
    private const MIN_INK_PIXELS = 40;

    /**
     * Ink covering more of the frame than this is not a signature — it is an
     * underexposed photo where the paper itself fell below the threshold.
     */
    private const MAX_INK_COVERAGE = 0.35;

    /**
     * How much darker than its local background a pixel must be to count as
     * ink. Low enough for a light ballpoint, high enough to ignore paper
     * texture, JPEG noise and the soft edge of a shadow.
     */
    private const MIN_INK_CONTRAST = 26;

    /**
     * The background estimate averages over roughly 1/this of the image's
     * width. It must be comfortably wider than a pen stroke — otherwise a
     * stroke raises its own background and erases itself — and narrower than
     * the lighting changes it exists to absorb.
     */
    private const BACKGROUND_WINDOW_DIVISOR = 24;

    /** A connected shape smaller than this is speckle, never a stroke. */
    private const MIN_SHAPE_PIXELS = 12;

    /** …as is one this much smaller than the largest shape in the image. */
    private const MIN_SHAPE_RATIO = 0.02;

    /** Quantile of ink pixels the crop box spans, ignoring sparse outliers. */
    private const CROP_QUANTILE = 0.005;

    /** Padding (px) around the cropped ink. */
    private const CROP_PADDING = 8;

    /** The colour extracted ink is rendered in. */
    private const INK_RGB = [20, 20, 30];

    /**
     * Extract the signature from a data-URL and store it as a transparent PNG
     * under $dir on the documents disk. Returns the disk-relative path, or null
     * when the value is not a decodable image or holds no legible signature.
     */
    public static function storeDataUrl(?string $dataUrl, string $dir): ?string
    {
        return self::store(self::decodeDataUrl($dataUrl), $dir);
    }

    /**
     * Extract and store raw image bytes (an uploaded photo, an ID zone crop).
     * Returns the disk-relative path, or null when no signature could be read.
     */
    public static function store(?string $binary, string $dir): ?string
    {
        if ($binary === null) {
            return null;
        }

        $ink = self::extractInk($binary);
        if ($ink === null) {
            return null;
        }

        $path = trim($dir, '/').'/'.Str::random(20).'.png';
        Storage::disk(self::disk())->put($path, $ink);

        return $path;
    }

    /**
     * The raw bytes of a signature data-URL, or null when undecodable.
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

    /**
     * Isolate the ink of a signature and return it as a cropped, transparent
     * PNG — the paper, the desk it was photographed on and the surrounding ID
     * artwork are all discarded.
     *
     * Returns null when the image holds no legible signature: a blank page, a
     * photo too dark to separate ink from paper, or a crop that is mostly ink
     * (which means the threshold caught the background, not a signature). The
     * caller is expected to reject the upload rather than fall back to the
     * original picture.
     */
    public static function extractInk(string $binary): ?string
    {
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }

        try {
            $image = self::downscale($image);
            $width = imagesx($image);
            $height = imagesy($image);

            $luminance = self::luminanceMap($image, $width, $height);
            $background = self::backgroundMap($image, $width, $height);

            $mask = self::inkMask($luminance, $background, $width, $height);
            if ($mask === null) {
                return null;
            }

            $box = self::inkBoundingBox($mask, $width, $height);
            if ($box === null) {
                return null;
            }

            return self::renderInk($mask, $box, $width);
        } finally {
            imagedestroy($image);
        }
    }

    public static function disk(): string
    {
        return (string) config('documents.disk', 'public');
    }


    /**
     * Shrink oversized photos before analysis: a phone photo is millions of
     * pixels, and stroke shape survives the downscale intact.
     */
    private static function downscale(\GdImage $image): \GdImage
    {
        $longest = max(imagesx($image), imagesy($image));
        if ($longest <= self::MAX_EDGE) {
            return $image;
        }

        $scaled = imagescale($image, (int) round(imagesx($image) * self::MAX_EDGE / $longest));
        if ($scaled === false) {
            return $image;
        }

        imagedestroy($image);

        return $scaled;
    }

    /**
     * Per-pixel luminance as one byte per pixel, row-major.
     *
     * A byte string rather than an array: a phone photo runs to a million
     * pixels, where a PHP array of ints costs ~80 bytes each and exhausts the
     * memory limit.
     *
     * Mostly-transparent pixels read as paper, so an already-extracted PNG
     * survives a second pass unchanged instead of having its transparent
     * background read as solid black.
     */
    private static function luminanceMap(\GdImage $image, int $width, int $height): string
    {
        $luminance = '';

        for ($y = 0; $y < $height; $y++) {
            $row = '';
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);

                if ((($rgba >> 24) & 0x7F) > 64) {
                    $row .= "\xFF";

                    continue;
                }

                $row .= chr((int) (
                    0.299 * (($rgba >> 16) & 0xFF)
                    + 0.587 * (($rgba >> 8) & 0xFF)
                    + 0.114 * ($rgba & 0xFF)
                ));
            }
            $luminance .= $row;
        }

        return $luminance;
    }

    /**
     * A smooth estimate of the local background (the paper) behind every pixel,
     * one byte per pixel.
     *
     * Produced by shrinking the image until strokes disappear into their
     * surroundings and stretching it back — a cheap box blur that GD does in C.
     * Comparing each pixel against its own neighbourhood, rather than one
     * global cutoff, is what makes uneven lighting survivable: a page lit from
     * one side, a shadow across a corner, and a dark desk behind the paper all
     * raise the local background with the pixels they darken, so none of them
     * register as ink.
     */
    private static function backgroundMap(\GdImage $image, int $width, int $height): string
    {
        $small = imagescale(
            $image,
            max(1, intdiv($width, self::BACKGROUND_WINDOW_DIVISOR)),
            max(1, intdiv($height, self::BACKGROUND_WINDOW_DIVISOR)),
            IMG_BILINEAR_FIXED,
        );
        if ($small === false) {
            return str_repeat("\xFF", $width * $height);
        }

        $blurred = imagescale($small, $width, $height, IMG_BILINEAR_FIXED);
        imagedestroy($small);
        if ($blurred === false) {
            return str_repeat("\xFF", $width * $height);
        }

        try {
            return self::luminanceMap($blurred, $width, $height);
        } finally {
            imagedestroy($blurred);
        }
    }

    /**
     * The ink mask, one byte per pixel ("\1" = ink): pixels meaningfully darker
     * than their own local background. Isolated pixels are dropped — JPEG
     * speckle and sensor noise would otherwise stretch the crop box across the
     * whole frame.
     *
     * Returns null when the result cannot be a signature: nothing darker than
     * the paper (a blank page), or so much of it that the "ink" must be the
     * background itself.
     */
    private static function inkMask(string $luminance, string $background, int $width, int $height): ?string
    {
        $pixels = $width * $height;
        $raw = str_repeat("\0", $pixels);
        $count = 0;

        for ($i = 0; $i < $pixels; $i++) {
            if (ord($background[$i]) - ord($luminance[$i]) >= self::MIN_INK_CONTRAST) {
                $raw[$i] = "\1";
                $count++;
            }
        }

        if ($count < self::MIN_INK_PIXELS) {
            return null;
        }

        // Mostly "ink" means the comparison caught the background, not a
        // signature — a photo of printed matter, or one badly out of focus.
        if ($count / $pixels > self::MAX_INK_COVERAGE) {
            return null;
        }

        $mask = str_repeat("\0", $pixels);
        $kept = 0;

        for ($i = 0; $i < $pixels; $i++) {
            if ($raw[$i] === "\0") {
                continue;
            }

            $x = $i % $width;
            $y = intdiv($i, $width);
            $neighbours = 0;

            for ($dy = -1; $dy <= 1; $dy++) {
                $ny = $y + $dy;
                if ($ny < 0 || $ny >= $height) {
                    continue;
                }
                for ($dx = -1; $dx <= 1; $dx++) {
                    $nx = $x + $dx;
                    if (($dx === 0 && $dy === 0) || $nx < 0 || $nx >= $width) {
                        continue;
                    }
                    if ($raw[$ny * $width + $nx] === "\1") {
                        $neighbours++;
                    }
                }
            }

            if ($neighbours >= 2) {
                $mask[$i] = "\1";
                $kept++;
            }
        }

        if ($kept < self::MIN_INK_PIXELS) {
            return null;
        }

        return self::dropBackgroundShapes($mask, $width, $height);
    }

    /**
     * Drop connected shapes that are not part of the signature: those running
     * off the edge of the frame, and specks far too small to be a stroke.
     *
     * The frame rule is what saves a photo with a shadow across one side or the
     * edge of the desk in view. Those are large, hard-edged regions — the local
     * threshold reads their boundary as a stroke and no crop heuristic can tell
     * it apart afterwards — but they always run off the picture, where a
     * signature written in the middle of a page does not.
     *
     * If applying the rule would leave nothing, it is abandoned and the mask is
     * kept whole: a tightly-cropped photo where the signature genuinely reaches
     * the edge is worth more than a strict rule.
     */
    private static function dropBackgroundShapes(string $mask, int $width, int $height): ?string
    {
        $pixels = $width * $height;
        $kept = str_repeat("\0", $pixels);
        $keptCount = 0;
        $largest = 0;
        $components = [];

        for ($start = 0; $start < $pixels; $start++) {
            if ($mask[$start] !== "\1") {
                continue;
            }

            // Flood-fill this shape, consuming it from the source mask.
            $queue = [$start];
            $mask[$start] = "\0";
            $shape = [];
            $touchesBorder = false;

            for ($head = 0; $head < count($queue); $head++) {
                $index = $queue[$head];
                $shape[] = $index;
                $x = $index % $width;
                $y = intdiv($index, $width);

                if ($x === 0 || $y === 0 || $x === $width - 1 || $y === $height - 1) {
                    $touchesBorder = true;
                }

                for ($dy = -1; $dy <= 1; $dy++) {
                    $ny = $y + $dy;
                    if ($ny < 0 || $ny >= $height) {
                        continue;
                    }
                    for ($dx = -1; $dx <= 1; $dx++) {
                        $nx = $x + $dx;
                        if ($nx < 0 || $nx >= $width) {
                            continue;
                        }
                        $neighbour = $ny * $width + $nx;
                        if ($mask[$neighbour] === "\1") {
                            $mask[$neighbour] = "\0";
                            $queue[] = $neighbour;
                        }
                    }
                }
            }

            $size = count($shape);
            $largest = max($largest, $size);
            $components[] = ['pixels' => $shape, 'size' => $size, 'border' => $touchesBorder];
        }

        // A stroke of the signature can be small, but not a fraction of the
        // largest one — that is dust, paper grain or a printed rule.
        $minimum = max(self::MIN_SHAPE_PIXELS, (int) ($largest * self::MIN_SHAPE_RATIO));

        foreach ($components as $component) {
            if ($component['border'] || $component['size'] < $minimum) {
                continue;
            }
            foreach ($component['pixels'] as $index) {
                $kept[$index] = "\1";
            }
            $keptCount += $component['size'];
        }

        if ($keptCount >= self::MIN_INK_PIXELS) {
            return $kept;
        }

        // Nothing survived the frame rule — fall back to every shape big enough.
        $kept = str_repeat("\0", $pixels);
        $keptCount = 0;
        foreach ($components as $component) {
            if ($component['size'] < $minimum) {
                continue;
            }
            foreach ($component['pixels'] as $index) {
                $kept[$index] = "\1";
            }
            $keptCount += $component['size'];
        }

        return $keptCount >= self::MIN_INK_PIXELS ? $kept : null;
    }

    /**
     * The crop box around the ink. Bounds are taken at a quantile of the ink
     * distribution rather than its outright min/max, so a stray speck — a shadow
     * in a corner, the edge of the page — cannot inflate the box to the whole
     * frame.
     *
     * @return array{0:int,1:int,2:int,3:int}|null  [x, y, width, height]
     */
    private static function inkBoundingBox(string $mask, int $width, int $height): ?array
    {
        $columns = array_fill(0, $width, 0);
        $rows = array_fill(0, $height, 0);
        $total = 0;

        $pixels = $width * $height;
        for ($i = 0; $i < $pixels; $i++) {
            if ($mask[$i] === "\1") {
                $columns[$i % $width]++;
                $rows[intdiv($i, $width)]++;
                $total++;
            }
        }

        if ($total === 0) {
            return null;
        }

        [$x1, $x2] = self::quantileSpan($columns, $total);
        [$y1, $y2] = self::quantileSpan($rows, $total);

        $x1 = max(0, $x1 - self::CROP_PADDING);
        $x2 = min($width - 1, $x2 + self::CROP_PADDING);
        $y1 = max(0, $y1 - self::CROP_PADDING);
        $y2 = min($height - 1, $y2 + self::CROP_PADDING);

        if ($x2 <= $x1 || $y2 <= $y1) {
            return null;
        }

        return [$x1, $y1, $x2 - $x1 + 1, $y2 - $y1 + 1];
    }

    /**
     * The [first, last] index spanning all but the outermost CROP_QUANTILE of a
     * counts histogram.
     *
     * @param  array<int,int>  $counts
     * @return array{0:int,1:int}
     */
    private static function quantileSpan(array $counts, int $total): array
    {
        $cut = $total * self::CROP_QUANTILE;

        $seen = 0;
        $first = 0;
        foreach ($counts as $index => $count) {
            $seen += $count;
            if ($seen > $cut) {
                $first = $index;
                break;
            }
        }

        $seen = 0;
        $last = count($counts) - 1;
        foreach (array_reverse($counts, true) as $index => $count) {
            $seen += $count;
            if ($seen > $cut) {
                $last = $index;
                break;
            }
        }

        return $first <= $last ? [$first, $last] : [$last, $first];
    }

    /**
     * Draw the masked ink onto a transparent canvas of the crop box, as a PNG.
     *
     * @param  array{0:int,1:int,2:int,3:int}  $box
     */
    private static function renderInk(string $mask, array $box, int $sourceWidth): ?string
    {
        [$boxX, $boxY, $boxWidth, $boxHeight] = $box;

        $canvas = imagecreatetruecolor($boxWidth, $boxHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle(
            $canvas, 0, 0, $boxWidth - 1, $boxHeight - 1,
            imagecolorallocatealpha($canvas, 0, 0, 0, 127),
        );

        $ink = imagecolorallocatealpha($canvas, self::INK_RGB[0], self::INK_RGB[1], self::INK_RGB[2], 0);

        for ($y = 0; $y < $boxHeight; $y++) {
            $sourceRow = ($boxY + $y) * $sourceWidth;
            for ($x = 0; $x < $boxWidth; $x++) {
                if ($mask[$sourceRow + $boxX + $x] === "\1") {
                    imagesetpixel($canvas, $x, $y, $ink);
                }
            }
        }

        ob_start();
        imagepng($canvas, null, 6);
        $png = ob_get_clean();
        imagedestroy($canvas);

        return $png !== false && $png !== '' ? $png : null;
    }
}
