<?php

namespace App\Models;

use App\Models\Form\formDescription;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    protected $fillable = [
        'form_template',
        'form_link',
        'form_description',
    ];

    protected $primaryKey = 'form_id';

    public function formDescription()
    {
        return $this->belongsTo(formDescription::class, 'form_description', 'form_desc_id');
    }
}
