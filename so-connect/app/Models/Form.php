<?php

namespace App\Models;

use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    /** @use HasFactory<\Database\Factories\FormFactory> */
    use HasFactory;

    protected $table = 'forms';

    protected $fillable = [
        'name',
        'description_text',
        'organization_id',
        'created_by',
        'is_active',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormDescription::class, 'form_id', 'id')->orderBy('field_order');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'form_id', 'id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form_id', 'id')->latest('submitted_at');
    }
}
