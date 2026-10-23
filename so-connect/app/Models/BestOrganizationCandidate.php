<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BestOrganizationCandidate extends Model
{
    public const ELIGIBLE = 'eligible';
    public const FINALIST = 'finalist';
    public const DISQUALIFIED = 'disqualified';
    public const WINNER = 'winner';

    protected $fillable = ['cycle_id', 'organization_id', 'organization_name_snapshot', 'organization_score_id', 'score_snapshot', 'rank_snapshot', 'status', 'review_notes', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array { return ['score_snapshot' => 'decimal:2', 'rank_snapshot' => 'integer', 'reviewed_at' => 'datetime']; }
    public function cycle(): BelongsTo { return $this->belongsTo(BestOrganizationCycle::class, 'cycle_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class, 'organization_id', 'organization_id'); }
    public function score(): BelongsTo { return $this->belongsTo(OrganizationScore::class, 'organization_score_id', 'organization_score_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by', 'user_id'); }
}
