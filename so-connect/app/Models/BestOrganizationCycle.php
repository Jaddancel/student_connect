<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BestOrganizationCycle extends Model
{
    public const DRAFT = 'draft';
    public const EVALUATION = 'evaluation';
    public const AWARDED = 'awarded';

    protected $fillable = ['semester_id', 'title', 'status', 'selection_notes', 'winner_organization_id', 'winner_name', 'winner_score', 'created_by', 'finalized_by', 'evaluation_started_at', 'finalized_at'];

    protected function casts(): array
    {
        return ['winner_score' => 'decimal:2', 'evaluation_started_at' => 'datetime', 'finalized_at' => 'datetime'];
    }

    public function semester(): BelongsTo { return $this->belongsTo(Semester::class, 'semester_id', 'semester_id'); }
    public function winner(): BelongsTo { return $this->belongsTo(Organization::class, 'winner_organization_id', 'organization_id'); }
    public function candidates(): HasMany { return $this->hasMany(BestOrganizationCandidate::class, 'cycle_id')->orderBy('rank_snapshot'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by', 'user_id'); }
    public function finalizer(): BelongsTo { return $this->belongsTo(User::class, 'finalized_by', 'user_id'); }
}
