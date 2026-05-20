<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationScore extends Model
{
    protected $table = 'organization_scores';

    protected $primaryKey = 'organization_score_id';

    protected $fillable = [
        'organization_id',
        'semester_id',
        'scored_by',
        'payload',
        'raw_scores',
        'total_weighted_score',
        'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'raw_scores' => 'array',
            'scored_at' => 'datetime',
            'total_weighted_score' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }

    public function scorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by', 'user_id');
    }
}
