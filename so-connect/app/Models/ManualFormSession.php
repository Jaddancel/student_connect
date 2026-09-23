<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A durable manual-filling draft. Holds the frozen partial PDF, the parsing
 * schemas, the returned scan and its parse result, and the lifecycle status —
 * resumed from the Drafts page (owner) or a private link (public forms).
 *
 * Never stores a plaintext password or a raw resume token: the token is kept
 * only as a hash, and digital-only sensitive fields are re-entered at review.
 */
class ManualFormSession extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'manual_form_sessions';

    public const STATUS_PREPARING = 'preparing';
    public const STATUS_AWAITING_SCAN = 'awaiting_scan';
    public const STATUS_PARSING = 'parsing';
    public const STATUS_REVIEW = 'review';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_EXPIRED = 'expired';

    /** Statuses that still count as an open draft (not yet finalized/dead). */
    public const OPEN_STATUSES = [
        self::STATUS_PREPARING,
        self::STATUS_AWAITING_SCAN,
        self::STATUS_PARSING,
        self::STATUS_REVIEW,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'form_id',
        'template_id',
        'template_version',
        'user_id',
        'public_token_hash',
        'status',
        'draft_payload',
        'known_fields',
        'extractable_fields',
        'digital_only_fields',
        'hidden_context',
        'baseline_schema',
        'session_schema',
        'partial_pdf_path',
        'partial_pdf_hash',
        'page_meta',
        'scan_paths',
        'aligned_page_paths',
        'parse_result',
        'parse_confidence',
        'parse_warnings',
        'parse_error',
        'parse_model',
        'form_submission_id',
        'expires_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'template_version' => 'integer',
            'draft_payload' => 'array',
            'known_fields' => 'array',
            'extractable_fields' => 'array',
            'digital_only_fields' => 'array',
            'hidden_context' => 'array',
            'baseline_schema' => 'array',
            'session_schema' => 'array',
            'page_meta' => 'array',
            'scan_paths' => 'array',
            'aligned_page_paths' => 'array',
            'parse_result' => 'array',
            'parse_confidence' => 'array',
            'parse_warnings' => 'array',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id', 'id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id', 'form_submission_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isPublic(): bool
    {
        return $this->user_id === null && $this->public_token_hash !== null;
    }
}
