<?php

namespace App\Models;

use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\RequestType;

class Form extends Model
{
    /** @use HasFactory<\Database\Factories\FormFactory> */
    use HasFactory;

    public const ROLE_LEVEL_OFFICER = 'officer';
    public const ROLE_LEVEL_PRESIDENT = 'president';
    public const ROLE_LEVEL_SUPERADMIN = 'superadmin';

    public const SIDEBAR_GROUP_OPTIONS = [
        self::ROLE_LEVEL_OFFICER,
        self::ROLE_LEVEL_PRESIDENT,
        self::ROLE_LEVEL_SUPERADMIN,
    ];

    protected $table = 'forms';

    protected $fillable = [
        'name',
        'description_text',
        'request_type_id',
        'sidebar_group',
        'organization_id',
        'created_by',
        'is_active',
        'is_published',
        'route_name',
        'system_function',
        'layout',
        'pdf_template',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_published' => 'boolean',
            'sidebar_group' => 'array',
            'layout' => 'array',
            'pdf_template' => 'array',
        ];
    }

    public static function sidebarGroupOptions(): array
    {
        return [
            self::ROLE_LEVEL_OFFICER => 'Officer',
            self::ROLE_LEVEL_PRESIDENT => 'President',
            self::ROLE_LEVEL_SUPERADMIN => 'Super Admin',
        ];
    }

    public static function sidebarGroupLabel(?string $sidebarGroup): string
    {
        return self::sidebarGroupOptions()[$sidebarGroup ?: ''] ?? ucfirst((string) ($sidebarGroup ?: self::ROLE_LEVEL_PRESIDENT));
    }

    public static function roleLevelOptions(): array
    {
        return self::sidebarGroupOptions();
    }

    /**
     * @param  string|array<int,string>|null  $roleLevel
     */
    public static function roleLevelLabel(string|array|null $roleLevel): string
    {
        if (is_array($roleLevel)) {
            $options = self::sidebarGroupOptions();
            $labels = array_map(fn ($r) => $options[$r] ?? ucfirst((string) $r), $roleLevel);

            return implode(', ', $labels);
        }

        return self::sidebarGroupLabel($roleLevel);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class, 'request_type_id', 'request_type_id');
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
