<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    /** @use HasFactory<\Database\Factories\TemplateFactory> */
    use HasFactory;
     
    protected $primaryKey = 'template_id';
    protected $fillable = [
        'template_description',
        'template_link',
    ];
     
    public function form(){
        return $this->hasMany(Form::class, 'form_template', 'template_id');
    }
}
