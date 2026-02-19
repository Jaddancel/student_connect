<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationDescription extends Model
{
    /** @use HasFactory<\Database\Factories\Evaluation\EvaluationDescriptionFactory> */
    use HasFactory;
    protected $table = 'evaluation_descriptions';
    protected $primaryKey = 'evaluation_description_id';
    protected $fillable = [
        'evaluation_item',
        'description',
        'comment',
        'grade',
    ];
    public $timestamps = false;
    public function evaluation_item()
    {
        return $this->belongsTo(EvaluationItem::class, 'evaluation_item', 'evaluation_item_id');
    }
}