<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringEvidenceAward extends Model
{
    protected $fillable = [
        'organization_score_id', 'criterion_key', 'criterion_label', 'instances', 'rule_points', 'awarded_points',
        'source_type', 'source_id', 'evidence_label', 'evidence_url', 'attachment_paths',
        'quality_score', 'quality_status', 'quality_notes', 'review_status', 'override_points',
        'override_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'attachment_paths' => 'array', 'quality_notes' => 'array', 'instances' => 'integer',
            'rule_points' => 'decimal:2', 'awarded_points' => 'decimal:2', 'override_points' => 'decimal:2', 'quality_score' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(OrganizationScore::class, 'organization_score_id', 'organization_score_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function effectivePoints(): float
    {
        return $this->override_points !== null ? (float) $this->override_points : (float) $this->awarded_points;
    }
}
