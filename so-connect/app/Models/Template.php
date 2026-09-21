<?php

namespace App\Models;

use App\Models\GeneratedDocument;
use App\Models\Template\TemplateDescription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    /** @use HasFactory<\Database\Factories\TemplateFactory> */
    use HasFactory;

    protected $table = 'templates';

    protected $fillable = [
        'form_id',
        'organization_id',
        'uploaded_by',
        'template_name',
        'docx_path',
        'version',
        'is_active',
        'manual_schema',
        'manual_schema_status',
        'manual_schema_error',
        'manual_schema_template_version',
        'manual_schema_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
            'manual_schema' => 'array',
            'manual_schema_template_version' => 'integer',
            'manual_schema_generated_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(TemplateDescription::class, 'template_id', 'id');
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class, 'template_id', 'id');
    }
}
