<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScoringCriterion extends Model
{
    protected $table = 'scoring_criteria';

    protected $primaryKey = 'scoring_criterion_id';

    protected $fillable = [
        'key', 'category_key', 'label', 'weight', 'sort_order', 'is_system', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function rule(): HasOne
    {
        return $this->hasOne(ScoringRule::class, 'criterion_id', 'scoring_criterion_id');
    }
}
