<?php

namespace App\Models;

use Database\Factories\EvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    /** @use HasFactory<EvaluationFactory> */
    use HasFactory;

    protected $table = 'evaluations';

    protected $fillable = [
        'description',
        'author',
        'score',
        'evaluated_at',
    ];

    protected $primaryKey = 'evaluation_id';

    public function authorOfEvaluation()
    {
        return $this->belongsTo(Officer::class, 'author', 'org_officer_id');
    }

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
        ];
    }
}
