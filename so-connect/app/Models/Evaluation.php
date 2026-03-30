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
    ];

    protected $primaryKey = 'evaluation_id';

    public function authorOfEvaluation()
    {
        return $this->belongsTo(Officer::class, 'author', 'officer_id');
    }

    public $timestamps = false;

    public function casts()
    {
        return [
            'evaluated_at' => 'dateTime',
        ];
    }
}
