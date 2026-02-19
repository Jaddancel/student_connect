<?php

namespace App\Models\Evaluation;

use App\Models\Evaluation\EvaluationDescription;
use Illuminate\Database\Eloquent\Model;

class EvaluationGrade extends Model
{
    protected $table = 'evaluation_grade_enum';
    protected $primaryKey = 'evaluation_grade_code';
    protected $fillable = [
        'grade_value',
    ];
    public $timestamps = false;
    public function evaluation_descriptions()
    {
        return $this->hasMany(EvaluationDescription::class, 'grade', 'evaluation_grade_code');
    }
}