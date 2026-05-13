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

    public const SIDEBAR_GROUP_GENERAL = 'general';
    public const SIDEBAR_GROUP_MEMBER = 'member';
    public const SIDEBAR_GROUP_OFFICER = 'officer';
    public const SIDEBAR_GROUP_PRESIDENT = 'president';
    public const SIDEBAR_GROUP_SUPERADMIN = 'superadmin';

    public const SIDEBAR_GROUP_OPTIONS = [
        self::SIDEBAR_GROUP_GENERAL,
        self::SIDEBAR_GROUP_MEMBER,
        self::SIDEBAR_GROUP_OFFICER,
        self::SIDEBAR_GROUP_PRESIDENT,
        self::SIDEBAR_GROUP_SUPERADMIN,
    ];

    protected $table = 'forms';

    protected $fillable = [
        'name',
        'description_text',
        'sidebar_group',
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

    public static function sidebarGroupOptions(): array
    {
        return array_combine(self::SIDEBAR_GROUP_OPTIONS, self::SIDEBAR_GROUP_OPTIONS);
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
