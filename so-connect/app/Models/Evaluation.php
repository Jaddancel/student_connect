<?php

namespace App\Models;

use App\Models\Member\President;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    /** @use HasFactory<\Database\Factories\EvaluationFactory> */
    use HasFactory;
    
    public $primaryKey = 'evaluation_id';
    protected $fillable = [
        'evaluation_author',
        'evaluation_item',
    ];

    public $timestamps = false;
    public function president()
    {
        return $this->belongsTo(President::class, 'evaluation_author', 'president_id');
    }
}
