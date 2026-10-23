<?php

namespace App\Services\Scanning;

use App\Services\DocumentVision\DocumentVisionClient;
use Illuminate\Support\Facades\Log;

/**
 * Reads the text zones of a scanned ID card or waiver with the document-vision
 * model, one zone per call. Far more tolerant of blur, glare and tilt than the
 * PaddleOCR pass in the OCR sidecar, which stays as the per-field fallback.
 *
 * On a photo too blurry to read, a vision model does not say so: it invents a
 * plausible value (a sample street address, "1999-01-01" as a birthday), and a
 * different one for each view of the same field. So a model reading is only
 * accepted when it is corroborated:
 *
 *  1. it matches PaddleOCR's text for that zone (clear photos: one call), or
 *  2. a second read of the zone in the other view (whole page vs. crop)
 *     matches it.
 *
 * Anything uncorroborated is dropped, so the field falls back to PaddleOCR or
 * stays blank for the user rather than being pre-filled with an invention.
 *
 * Zones are authored in a template's reference frame and mapped onto the
 * photo's own resolution. The whole read is bounded by a time budget so a live
 * scan stays responsive; zones it cannot reach in time are left to the
 * fallback.
 */
class VisionZoneReader
{
    /** The zone cropped out on its own and upscaled when small. */
    public const VIEW_CROP = 'crop';

    /**
     * The whole image at its own resolution with the zone outlined in red.
     * Best first view for small documents such as ID cards: the surrounding
     * text helps the model make out blurry letters.
     */
    public const VIEW_PAGE = 'page';

    /** Normalized similarity at which two readings count as the same text. */
    private const AGREEMENT = 0.75;

    private const MAX_CROP_EDGE = 1024;

    /** Longest edge of a whole-page view; larger photos are scaled down. */
    private const PAGE_EDGE = 1600;

    public function __construct(private readonly DocumentVisionClient $vision) {}

    public static function enabled(): bool
    {
        return (bool) config('services.document_vision.zone_reading', true)
            && (string) config('services.document_vision.provider') === 'ollama'
            && (string) config('services.document_vision.url', '') !== '';
    }

    /**
     * @param  array{width:int,height:int}  $reference  template frame size
     * @param  array<int,array{name:string,label?:?string,field?:?string,x1:int|float,y1:int|float,x2:int|float,y2:int|float,regex?:?string,kind?:?string}>  $zones  text zones only
     * @param  array<string,string>  $ocrTexts  zone name => PaddleOCR text, used to corroborate
     * @param  string  $primary  view read first: VIEW_PAGE or VIEW_CROP
     * @return array<string,array{value:string,confidence:?float}> zone name => corroborated value
     */
    public function read(string $imageBytes, array $reference, array $zones, string $document, string $primary = self::VIEW_CROP, array $ocrTexts = []): array
    {
        if (! self::enabled() || $zones === [] || $imageBytes === '') {
            return [];
        }

        $refW = (int) ($reference['width'] ?? 0);
        $refH = (int) ($reference['height'] ?? 0);
        $photo = @imagecreatefromstring($imageBytes);
        if ($refW <= 0 || $refH <= 0 || ! $photo instanceof \GdImage) {
            return [];
        }

        $deadline = microtime(true) + max(1, (int) config('services.document_vision.zone_budget_seconds', 25));
        $page = $this->pageCanvas($photo);
        $secondary = $primary === self::VIEW_PAGE ? self::VIEW_CROP : self::VIEW_PAGE;
        $values = [];

        try {
            foreach ($zones as $zone) {
                $name = (string) ($zone['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $first = $this->readView($primary, $photo, $page, $zone, $refW, $refH, $document, $deadline);
                if ($first === false) {
                    break;
                }
                if ($first === null) {
                    continue;
                }

                $ocr = trim((string) ($ocrTexts[$name] ?? ''));
                if ($ocr !== '' && self::similarity($first['value'], $ocr) >= self::AGREEMENT) {
                    $values[$name] = $first;

                    continue;
                }

                $second = $this->readView($secondary, $photo, $page, $zone, $refW, $refH, $document, $deadline);
                if ($second === false) {
                    break;
                }
                if ($second !== null && self::similarity($first['value'], $second['value']) >= self::AGREEMENT) {
                    $values[$name] = $first;

                    continue;
                }

                Log::info('Vision zone reading: uncorroborated reading dropped', [
                    'zone' => $name,
                    'readings' => array_filter([$first['value'], $second['value'] ?? null, $ocr]),
                ]);
            }
        } finally {
            unset($page, $photo);
        }

        return $values;
    }

    /**
     * One model read of a zone in the given view: the regex-checked value,
     * null when nothing usable was read, or false when reading must stop
     * (model unreachable or time budget spent).
     *
     * @param  array<string,mixed>  $zone
     * @return array{value:string,confidence:?float}|null|false
     */
    private function readView(string $view, \GdImage $photo, \GdImage $page, array $zone, int $refW, int $refH, string $document, float $deadline): array|null|false
    {
        $remaining = (int) floor($deadline - microtime(true));
        if ($remaining < 2) {
            Log::info('Vision zone reading: time budget used up; remaining zones fall back to OCR.');

            return false;
        }

        $image = $view === self::VIEW_PAGE
            ? $this->outlined($page, $zone, $refW, $refH)
            : $this->crop($photo, $zone, $refW, $refH);
        if ($image === null) {
            return null;
        }

        $name = (string) $zone['name'];
        $result = $this->vision->readZone([
            'image' => base64_encode($image),
            'label' => (string) ($zone['label'] ?? $name),
            'key' => (string) ($zone['field'] ?? $name),
            'kind' => (string) ($zone['kind'] ?? self::kindFor($zone)),
            'document' => $document,
            'view' => $view,
            'timeout' => min((int) config('services.document_vision.timeout', 180), $remaining),
        ]);

        if (! ($result['ok'] ?? false)) {
            return in_array($result['status'] ?? null, ['unavailable', 'unreachable'], true) ? false : null;
        }

        $value = self::applyRegex((string) ($zone['regex'] ?? ''), trim((string) ($result['value'] ?? '')));

        return $value !== null && $value !== ''
            ? ['value' => $value, 'confidence' => $result['confidence'] ?? null]
            : null;
    }

    /**
     * Similarity in [0,1] of two readings, ignoring case, spacing and
     * punctuation ("GUIDES, MARY JOY E." vs "Guides Mary Joy E" → 1.0).
     */
    public static function similarity(string $a, string $b): float
    {
        $norm = fn (string $v) => (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtoupper($v));
        $a = $norm($a);
        $b = $norm($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        $max = max(mb_strlen($a), mb_strlen($b));
        if ($max > 255) {
            similar_text($a, $b, $percent);

            return $percent / 100;
        }

        return max(0.0, 1.0 - levenshtein($a, $b) / $max);
    }

    /**
     * The whole photo for a page view: its own pixels (upscaling a small,
     * blurry photo made the model misread it), scaled down only when its long
     * edge exceeds PAGE_EDGE.
     */
    private function pageCanvas(\GdImage $photo): \GdImage
    {
        $width = imagesx($photo);
        $height = imagesy($photo);
        if (max($width, $height) <= self::PAGE_EDGE) {
            return $photo;
        }

        $scale = self::PAGE_EDGE / max($width, $height);
        $scaled = imagescale($photo, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);

        return $scaled !== false ? $scaled : $photo;
    }

    /**
     * Lanczos upscale via Imagick, or null when Imagick is unavailable. GD's
     * bicubic smears small text so badly that the model misread a blurry
     * card's birth year and invented an address from it; sending the native
     * pixels beats a poor upscale.
     */
    private function upscale(\GdImage $image, int $width, int $height): ?\GdImage
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            ob_start();
            imagepng($image);
            $imagick = new \Imagick;
            $imagick->readImageBlob((string) ob_get_clean());
            $imagick->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
            $imagick->setImageFormat('png');
            $scaled = @imagecreatefromstring($imagick->getImageBlob());
            $imagick->clear();

            return $scaled instanceof \GdImage ? $scaled : null;
        } catch (\Throwable $e) {
            Log::info('Vision zone reading: upscale failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * PNG bytes of the whole page with the zone (slightly padded) outlined in
     * red, or null for a degenerate zone.
     *
     * @param  array<string,mixed>  $zone
     */
    private function outlined(\GdImage $page, array $zone, int $refW, int $refH): ?string
    {
        $width = imagesx($page);
        $height = imagesy($page);
        $sx = $width / $refW;
        $sy = $height / $refH;

        $x1 = (int) floor((float) $zone['x1'] * $sx) - 4;
        $y1 = (int) floor((float) $zone['y1'] * $sy) - 4;
        $x2 = (int) ceil((float) $zone['x2'] * $sx) + 4;
        $y2 = (int) ceil((float) $zone['y2'] * $sy) + 4;
        if ($x2 <= $x1 || $y2 <= $y1) {
            return null;
        }

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            return null;
        }
        imagecopy($canvas, $page, 0, 0, 0, 0, $width, $height);
        imagesetthickness($canvas, 3);
        imagerectangle(
            $canvas,
            max(0, $x1), max(0, $y1), min($width - 1, $x2), min($height - 1, $y2),
            (int) imagecolorallocate($canvas, 255, 0, 0),
        );

        ob_start();
        imagepng($canvas);
        $bytes = (string) ob_get_clean();

        return $bytes !== '' ? $bytes : null;
    }

    /**
     * What a zone holds, inferred from its destination field and label, so
     * the model can be told to expect a name, a date, an ID number, etc.
     *
     * @param  array<string,mixed>  $zone
     */
    public static function kindFor(array $zone): string
    {
        $text = strtolower(($zone['field'] ?? '').' '.($zone['name'] ?? '').' '.($zone['label'] ?? ''));

        return match (true) {
            (bool) preg_match('/student.?(id|no|number)|id.?(no|number)|\bid\b/', $text) => 'id_number',
            (bool) preg_match('/birth|bday|date/', $text) => 'date',
            (bool) preg_match('/time/', $text) => 'time',
            (bool) preg_match('/address/', $text) => 'address',
            (bool) preg_match('/name/', $text) => 'name',
            default => 'text',
        };
    }

    /**
     * Apply a zone's extraction regex the way the sidecar does (first match),
     * or return the text unchanged when there is none. Zone regexes are
     * authored for Python; an unusable one leaves the text as read. Null when
     * the regex does not match, so the field falls back to OCR.
     */
    public static function applyRegex(string $pattern, string $text): ?string
    {
        if ($pattern === '') {
            return $text;
        }

        $result = @preg_match('~'.str_replace('~', '\~', $pattern).'~u', $text, $match);
        if ($result === false) {
            return $text;
        }

        return $result === 1 ? $match[0] : null;
    }

    /**
     * PNG bytes of a zone cropped from the photo at its own resolution, padded
     * and upscaled to a legible size; null for a degenerate zone.
     *
     * @param  array<string,mixed>  $zone
     */
    private function crop(\GdImage $photo, array $zone, int $refW, int $refH): ?string
    {
        $width = imagesx($photo);
        $height = imagesy($photo);
        $sx = $width / $refW;
        $sy = $height / $refH;

        $x1 = (float) $zone['x1'] * $sx;
        $y1 = (float) $zone['y1'] * $sy;
        $x2 = (float) $zone['x2'] * $sx;
        $y2 = (float) $zone['y2'] * $sy;
        if ($x2 <= $x1 || $y2 <= $y1) {
            return null;
        }

        $padX = ($x2 - $x1) * 0.06 + 4;
        $padY = ($y2 - $y1) * 0.15 + 4;
        $left = (int) max(0, floor($x1 - $padX));
        $top = (int) max(0, floor($y1 - $padY));
        $cropW = (int) min($width, ceil($x2 + $padX)) - $left;
        $cropH = (int) min($height, ceil($y2 + $padY)) - $top;
        if ($cropW < 4 || $cropH < 4) {
            return null;
        }

        $crop = imagecrop($photo, ['x' => $left, 'y' => $top, 'width' => $cropW, 'height' => $cropH]);
        if ($crop === false) {
            return null;
        }

        try {
            $minEdge = max(28, (int) config('services.document_vision.crop_min_edge', 112));
            $scale = min(self::MAX_CROP_EDGE / max($cropW, $cropH), max(1.0, $minEdge / min($cropW, $cropH)));
            if ($scale > 1.01) {
                $crop = $this->upscale($crop, max(1, (int) round($cropW * $scale)), max(1, (int) round($cropH * $scale))) ?? $crop;
            } elseif ($scale < 0.99) {
                $scaled = imagescale($crop, max(1, (int) round($cropW * $scale)), max(1, (int) round($cropH * $scale)), IMG_BICUBIC);
                if ($scaled !== false) {
                    $crop = $scaled;
                }
            }

            ob_start();
            imagepng($crop);
            $bytes = (string) ob_get_clean();

            return $bytes !== '' ? $bytes : null;
        } finally {
            unset($crop);
        }
    }
}
