<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualFormSessionDocument extends Model
{
    protected $fillable = [
        'manual_form_session_id', 'template_id', 'template_version', 'position',
        'status', 'baseline_schema', 'session_schema', 'partial_pdf_path',
        'partial_pdf_hash', 'page_meta', 'scan_paths', 'aligned_page_paths',
        'parse_result', 'parse_confidence', 'parse_warnings', 'parse_error', 'parse_model',
    ];

    protected function casts(): array
    {
        return [
            'baseline_schema' => 'array', 'session_schema' => 'array',
            'page_meta' => 'array', 'scan_paths' => 'array',
            'aligned_page_paths' => 'array', 'parse_result' => 'array',
            'parse_confidence' => 'array', 'parse_warnings' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ManualFormSession::class, 'manual_form_session_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
