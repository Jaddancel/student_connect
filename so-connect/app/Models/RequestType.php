<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestType extends Model
{
    use HasFactory;

    public const CATEGORY_ORGANIZATION = 'Organization Requests';
    public const CATEGORY_EVENT = 'Event Requests';
    public const CATEGORY_ROLE_SECURITY = 'Role and Security Requests';

    public const CATEGORY_LABELS = [
        self::CATEGORY_ORGANIZATION => 'Organization',
        self::CATEGORY_EVENT => 'Event',
        self::CATEGORY_ROLE_SECURITY => 'Role and Security',
    ];

    public const CATEGORY_OPTIONS = [
        self::CATEGORY_ORGANIZATION,
        self::CATEGORY_EVENT,
        self::CATEGORY_ROLE_SECURITY,
    ];

    public const SYSTEM_KEY_MEMBERSHIP = 'membership';
    public const SYSTEM_KEY_EVENT = 'event';
    public const SYSTEM_KEY_ROLE_CHANGE = 'role_change';
    public const SYSTEM_KEY_FORM_GENERATION = 'form_generation';
    public const SYSTEM_KEY_FORM_ACCESS = 'form_access';
    public const SYSTEM_KEY_FORM_UPLOAD = 'form_upload';
    public const SYSTEM_KEY_PROFILE_MATCH = 'profile_match';

    protected $table = 'request_types';

    protected $primaryKey = 'request_type_id';

    protected $fillable = [
        'name',
        'category',
        'system_key',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function categoryOptions(): array
    {
        return array_combine(
            self::CATEGORY_OPTIONS,
            array_map(fn (string $category) => self::categoryLabelForContext($category, 'requests'), self::CATEGORY_OPTIONS)
        );
    }

    public static function categoryBaseLabel(?string $category): string
    {
        if (! is_string($category) || $category === '') {
            return 'Category';
        }

        return self::CATEGORY_LABELS[$category] ?? preg_replace('/\s+Requests$/i', '', $category) ?: $category;
    }

    public static function categoryLabelForContext(?string $category, string $context = 'requests'): string
    {
        $baseLabel = self::categoryBaseLabel($category);

        return match ($context) {
            'forms' => $baseLabel.' Forms',
            'requests' => $baseLabel.' Requests',
            default => $baseLabel,
        };
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class, 'request_type_id', 'request_type_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'request_type_id', 'request_type_id');
    }
}
