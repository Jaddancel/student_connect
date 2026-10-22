<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LevelThreshold extends Model
{
    public const ORGANIZATION = 'organization';
    public const STUDENT = 'student';

    protected $fillable = ['scope', 'level_number', 'label', 'minimum_score', 'color'];

    protected function casts(): array
    {
        return ['level_number' => 'integer', 'minimum_score' => 'decimal:2'];
    }
}
