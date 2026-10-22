<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentCategory extends Model
{
    public const COLORS = ['brand', 'blue', 'purple', 'success', 'warning', 'error', 'gray'];

    protected $fillable = ['name', 'slug', 'description', 'icon', 'color', 'is_active', 'display_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'display_order' => 'integer'];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class, 'category_id');
    }

    public function reportTemplates(): HasMany
    {
        return $this->hasMany(ReportTemplate::class, 'category_id');
    }
}
