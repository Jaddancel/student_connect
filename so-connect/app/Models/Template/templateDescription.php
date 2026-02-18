<?php

namespace App\Models\Template;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class templateDescription extends Model
{
    /** @use HasFactory<\Database\Factories\Template\templateDescriptionFactory> */
    use HasFactory;
    public $timestamps = false;
    protected $primaryKey = 'template_description_id';
    protected $fillable = [
        'template_title',
        'template_description_text',
    ];

    public function template(){
        return $this->hasOne(Template::class, 'template_description', 'template_description_id');
    }
}
