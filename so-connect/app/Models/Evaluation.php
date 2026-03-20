<?php

namespace App\Models;

use Database\Factories\EvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    /** @use HasFactory<EvaluationFactory> */
    use HasFactory;

    protected $primaryKey = 'evaluation_id';

    protected $table = 'evaluations';

    protected $fillable = [
        'evaluation_description',
        'evaluation_author',
        'evaluation_score',
    ];

    public function evaluationDescription()
    {
        return $this->belongsTo(Evaluation\EvaluationDescription::class, 'evaluation_description', 'evaluation_description_id');
    }
}
