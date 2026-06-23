<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormScan extends Model
{
    protected $table = 'form_scans';

    protected $fillable = [
        'form_id',
        'uploaded_by',
        'scan_image_path',
        'status',
        'ocr_raw',
        'ocr_result',
        'error_message',
        'job_id',
    ];

    protected function casts(): array
    {
        return [
            'ocr_raw' => 'array',
            'ocr_result' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }
}
