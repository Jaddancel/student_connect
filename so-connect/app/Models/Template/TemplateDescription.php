<?php

namespace App\Models\Template;

use App\Models\Form\FormDescription;
use App\Models\Template as FormTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateDescription extends Model
{
    /** @use HasFactory<\Database\Factories\Template\TemplateDescriptionFactory> */
    use HasFactory;

    protected $table = 'template_descriptions';

    protected $fillable = [
        'template_id',
        'form_description_id',
        'placeholder_key',
        'field_key',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'template_id', 'id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormDescription::class, 'form_description_id', 'id');
    }
}
