<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelHistory extends Model
{
    protected $fillable = [
        'scope', 'semester_id', 'subject_id', 'raw_score', 'normalized_score',
        'level_number', 'level_label', 'threshold_snapshot', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_score' => 'decimal:2', 'normalized_score' => 'decimal:2',
            'threshold_snapshot' => 'decimal:2', 'level_number' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }
}
