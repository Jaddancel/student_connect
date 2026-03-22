<?php

namespace App\Models\Form;

use App\Models\Form;
use Database\Factories\Form\formDescriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class formDescription extends Model
{
    /** @use HasFactory<formDescriptionFactory> */
    use HasFactory;

    const CREATED_AT = 'form_created_at';

    const UPDATED_AT = 'form_updated_at';

    private $table = 'form_descriptions';

    protected $primaryKey = 'form_desc_id';

    protected $fillable = [
        'form_name',
        'form_desc_text',
        'form_category',
    ];

    public function form()
    {
        return $this->hasOne(Form::class, 'form_description', 'form_desc_id');
    }
}
