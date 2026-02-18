<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;

class evaluationGrade extends Model
{
    protected $table = 'evaluation_grade_enum';
    protected $primaryKey = 'evaluation_grade_code';
    protected $fillable = [
        'grade_value',
    ];
    public $timestamps = false;
    public function evaluation_descriptions()
    {
        return $this->hasMany(evaluationDescription::class, 'grade', 'evaluation_grade_code');
    }
}
