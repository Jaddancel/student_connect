<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A SuperAdmin-authored "ID template": a reference ID image plus a set of named
 * rectangular zones (in native image pixels) that the OCR sidecar crops and
 * reads. Exactly one active template is flagged `is_default` and drives the
 * live scanner.
 *
 * Not to be confused with the unrelated DOCX {@see \App\Models\Template}.
 */
class IdTemplate extends Model
{
    use HasFactory;

    protected $table = 'id_templates';

    protected $primaryKey = 'id_template_id';

    protected $fillable = [
        'name',
        'image_path',
        'image_width',
        'image_height',
        'zones',
        'back_image_path',
        'back_image_width',
        'back_image_height',
        'back_zones',
        'orientation',
        'is_active',
        'is_default',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'zones' => 'array',
            'back_zones' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'image_width' => 'integer',
            'image_height' => 'integer',
            'back_image_width' => 'integer',
            'back_image_height' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * The template the live scanner uses. Prefers the active+default template,
     * but falls back to the most-recent *active* template so the scanner still
     * works when an admin activated a template without explicitly flagging it as
     * default (the previous strict active+default rule made scanning "fail
     * instantly" in that common case).
     */
    public static function scannerTemplate(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('id_template_id')
            ->first();
    }

    /**
     * All active templates, best-first (default template leads), shaped for the
     * scan wizard's template chooser: id, name, orientation, a public photo URL
     * of the front reference image, and which sides carry a signature zone.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function scannerChoices(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('id_template_id')
            ->get()
            ->map(fn (self $template) => [
                'id' => (int) $template->getKey(),
                'name' => (string) $template->name,
                'orientation' => $template->orientation === 'horizontal' ? 'horizontal' : 'vertical',
                'photo' => $template->image_path
                    ? \Illuminate\Support\Facades\Storage::disk(config('documents.disk', 'public'))->url($template->image_path)
                    : null,
                'signature_sides' => [
                    'front' => $template->hasSignatureZone('front'),
                    'back' => $template->hasSignatureZone('back'),
                ],
            ])
            ->values()
            ->all();
    }

    /** Whether a side has at least one signature-type zone. */
    public function hasSignatureZone(string $side): bool
    {
        foreach ($this->zonesForSide($side) as $zone) {
            if (($zone['type'] ?? 'text') === 'signature') {
                return true;
            }
        }

        return false;
    }

    /**
     * The zone list for a side ('front' | 'back'), in native image pixels.
     *
     * @return array<int,array<string,mixed>>
     */
    public function zonesForSide(string $side = 'front'): array
    {
        return (array) ($side === 'back' ? $this->back_zones : $this->zones);
    }

    /**
     * The frozen request payload the OCR sidecar consumes (see
     * docs/ocr-template-contract.md), built for one side of the ID. The sidecar
     * contract is single-reference; the two-sided template scans each side with
     * its own payload.
     *
     * @return array<string,mixed>
     */
    public function toScannerPayload(string $side = 'front'): array
    {
        $isBack = $side === 'back';

        return [
            'template_id' => $this->getKey(),
            'name' => $this->name,
            'reference' => [
                'width' => (int) ($isBack ? $this->back_image_width : $this->image_width),
                'height' => (int) ($isBack ? $this->back_image_height : $this->image_height),
            ],
            'zones' => array_map(static function (array $zone): array {
                return [
                    'name' => $zone['name'] ?? '',
                    'label' => $zone['label'] ?? ($zone['name'] ?? ''),
                    'x1' => (int) ($zone['x1'] ?? 0),
                    'y1' => (int) ($zone['y1'] ?? 0),
                    'x2' => (int) ($zone['x2'] ?? 0),
                    'y2' => (int) ($zone['y2'] ?? 0),
                    'regex' => $zone['regex'] ?? null,
                    'field' => $zone['field'] ?? ($zone['name'] ?? ''),
                    // 'signature' zones come back as an image crop, not OCR text.
                    'type' => ($zone['type'] ?? 'text') === 'signature' ? 'signature' : 'text',
                ];
            }, $this->zonesForSide($side)),
        ];
    }
}
