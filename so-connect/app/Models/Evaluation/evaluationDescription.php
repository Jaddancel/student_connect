<?php

namespace App\Models\Evaluation;

use App\Models\Evaluation;
use Illuminate\Database\Eloquent\Model;

class evaluationDescription extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'evaluation_description_id';

    protected $table = 'evaluation_descriptions';

    protected $fillable = [
        'evaluation_description_text',
        'evaluation_created_at',
    ];

    public $casts = [
        'evaluation_created_at' => 'datetime',
    ];

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'evaluation_description', 'evaluation_description_id');
    }
}
