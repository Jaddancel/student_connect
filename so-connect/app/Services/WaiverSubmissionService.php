<?php

namespace App\Services;

use App\Models\Form\FormDescription;
use App\Models\IdTemplate;
use App\Support\SignatureImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Submit-time handling for WAIVER_SCAN fields: store the scanned image and
 * authoritatively re-validate it server-side (the client-side check is only
 * advisory). Re-validation is best-effort — a scanner outage never blocks a
 * submission; the review UI just shows it as unvalidated.
 */
class WaiverSubmissionService
{
    public function __construct(
        private readonly OcrClient $ocr,
        private readonly WaiverValidationService $validator,
    ) {}

    /**
     * @return array{path: ?string, validation: ?array<string,mixed>}
     */
    public function process(?string $value, FormDescription $field): array
    {
        // Already a stored path (or empty) — nothing to store/validate.
        if ($value === null || $value === '' || ! str_starts_with($value, 'data:image')) {
            return ['path' => $value, 'validation' => null];
        }

        return ['path' => $this->storeScan($value), 'validation' => $this->revalidate($value, $field)];
    }

    /**
     * Store a scanned waiver as-is.
     *
     * Deliberately NOT {@see SignatureImage::storeDataUrl()}: that extracts the
     * ink of a signature and discards everything around it. A waiver scan is
     * the whole signed page — the printed text, the stamp and the reviewer's
     * view of the document all have to survive.
     */
    private function storeScan(string $dataUrl): ?string
    {
        $binary = SignatureImage::decodeDataUrl($dataUrl);
        if ($binary === null) {
            return null;
        }

        $path = 'waivers/'.now()->format('Y/m').'/'.Str::random(20).'.png';
        Storage::disk(SignatureImage::disk())->put($path, $binary);

        return $path;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function revalidate(string $dataUrl, FormDescription $field): ?array
    {
        $png = SignatureImage::decodeDataUrl($dataUrl);
        if ($png === null) {
            return null;
        }

        $template = $this->resolveTemplate($field);
        if ($template === null) {
            return ['valid' => null, 'note' => 'no waiver template'];
        }

        try {
            $scan = $this->ocr->scanWaiver($png, $template);
            if (! ($scan['ok'] ?? false)) {
                return ['valid' => null, 'note' => 'scanner unavailable'];
            }

            $expected = (array) (($field->field_options ?? [])['waiver_expected'] ?? []);

            return $this->validator->validate(
                (array) ($scan['fields'] ?? []),
                $expected,
                ['stamp' => (bool) ($scan['stamp'] ?? false), 'signature' => (bool) ($scan['signature'] ?? false)],
            );
        } catch (\Throwable) {
            return ['valid' => null, 'note' => 'not validated'];
        }
    }

    /**
     * @return array{reference:array{width:int,height:int}, zones:array<int,mixed>}|null
     */
    private function resolveTemplate(FormDescription $field): ?array
    {
        $opts = (array) ($field->field_options ?? []);

        $template = IdTemplate::query()
            ->where('kind', 'waiver')
            ->when($opts['waiver_template_id'] ?? null, fn ($q, $id) => $q->where('id_template_id', $id))
            ->orderByDesc('is_default')
            ->orderByDesc('id_template_id')
            ->first();

        if ($template === null) {
            return null;
        }

        return [
            'reference' => ['width' => (int) $template->image_width, 'height' => (int) $template->image_height],
            'zones' => array_values((array) $template->zones),
        ];
    }
}
