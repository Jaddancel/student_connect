<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringRule extends Model
{
    protected $table = 'scoring_rules';

    protected $primaryKey = 'scoring_rule_id';

    protected $fillable = ['criterion_id', 'workspace', 'trigger', 'enabled', 'updated_by'];

    protected function casts(): array
    {
        return [
            'workspace' => 'array',
            'trigger' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(ScoringCriterion::class, 'criterion_id', 'scoring_criterion_id');
    }
}
