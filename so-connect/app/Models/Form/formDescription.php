<?php

namespace App\Models\Form;

use App\Models\Form;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class formDescription extends Model
{
    /** @use HasFactory<\Database\Factories\Form\formDescriptionFactory> */
    use HasFactory;
    
    protected $primaryKey = 'form_description_id';
    protected $fillable = [
        'form_name',
        'form_description_text',
        'form_organizations',
    ];

    public const CREATED_AT = 'form_created_at';
    public const UPDATED_AT = 'form_updated_at';
    
    public function organization(){
        return $this->belongsTo(\App\Models\Organization::class, 'form_organizations', 'organization_id');
    }

    public function form(){
        return $this->hasMany(Form::class, 'form_description', 'form_description_id');
    }
}
