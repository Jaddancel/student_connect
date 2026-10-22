<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoringRubricVersion extends Model
{
    protected $fillable = ['version', 'content_hash', 'snapshot', 'created_by', 'published_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version' => 'integer', 'published_at' => 'datetime'];
    }

    public function scores(): HasMany
    {
        return $this->hasMany(OrganizationScore::class, 'rubric_version_id');
    }
}
