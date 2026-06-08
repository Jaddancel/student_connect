<?php

namespace App\Models;

use App\Models\Form;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormScan extends Model
{
    /** @use HasFactory<\Database\Factories\FormScanFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

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
