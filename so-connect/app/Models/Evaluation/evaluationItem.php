<?php

namespace App\Models\Evaluation;

use App\Models\Evaluation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class evaluationItem extends Model
{
    /** @use HasFactory<\Database\Factories\Evaluation\evaluationItemFactory> */
    use HasFactory;

    public $primaryKey = 'evaluation_item_id';
    protected $fillable = [
        'evaluation_description',
        'evaluation_grade',
        'evaluation_created_at',
    ];

    public $timestamps = false;

    public function evaluation()
    {
        return $this->hasOne(Evaluation::class, 'evaluation_item', 'evaluation_item_id');
    }
}
