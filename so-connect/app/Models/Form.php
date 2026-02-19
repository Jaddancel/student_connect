<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    /** @use HasFactory<\Database\Factories\FormFactory> */
    use HasFactory;
    
    protected $primaryKey = 'form_id';
    protected $fillable = [
        'form_template',
        'form_link',
        'form_description',
    ];

}
