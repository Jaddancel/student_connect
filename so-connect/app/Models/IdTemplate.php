<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    protected $table = 'id_templates';

    protected $primaryKey = 'id_template_id';

    protected $fillable = [
        'name',
        'image_path',
        'image_width',
        'image_height',
        'zones',
        'is_active',
        'is_default',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'zones' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'image_width' => 'integer',
            'image_height' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * The template the live scanner uses: active + default.
     */
    public static function scannerTemplate(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    /**
     * The frozen request payload the OCR sidecar consumes (see
     * docs/ocr-template-contract.md).
     *
     * @return array<string,mixed>
     */
    public function toScannerPayload(): array
    {
        return [
            'template_id' => $this->getKey(),
            'name' => $this->name,
            'reference' => [
                'width' => (int) $this->image_width,
                'height' => (int) $this->image_height,
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
                ];
            }, (array) $this->zones),
        ];
    }
}
