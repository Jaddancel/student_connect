<?php

namespace App\Models\Form;

use App\Models\Form;
use App\Models\Template\TemplateDescription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormDescription extends Model
{
    /** @use HasFactory<\Database\Factories\Form\FormDescriptionFactory> */
    use HasFactory;

    protected $table = 'form_descriptions';

    protected $fillable = [
        'form_id',
        'field_key',
        'field_label',
        'field_type',
        'is_required',
        'field_order',
        'placeholder_hint',
        'field_options',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'field_order' => 'integer',
            'field_options' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(TemplateDescription::class, 'form_description_id', 'id');
    }
}
