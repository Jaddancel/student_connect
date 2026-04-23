<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedDocument extends Model
{
    use HasFactory;

    protected $table = 'generated_documents';

    protected $primaryKey = 'generated_document_id';

    protected $fillable = [
        'form_submission_id',
        'template_id',
        'request_id',
        'document_id',
        'generated_by',
        'docx_path',
        'pdf_path',
        'status',
        'failure_reason',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            self::CREATED_AT => 'datetime',
            self::UPDATED_AT => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id', 'form_submission_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id', 'id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'request_id', 'request_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id', 'document_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by', 'user_id');
    }
}
