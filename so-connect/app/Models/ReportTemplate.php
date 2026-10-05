<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-authored report: a token `definition` (see
 * {@see \App\Reports\ReportDefinitionValidator}) printed through one or more
 * .docx template slots ({@see Template} rows with report_template_id).
 */
class ReportTemplate extends Model
{
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_ADMINS = 'admins';

    public const AUDIENCE_OFFICERS = 'officers';

    protected $table = 'report_templates';

    /**
     * @return array<string, string>
     */
    public static function audiences(): array
    {
        return [
            self::AUDIENCE_ALL => 'All users',
            self::AUDIENCE_ADMINS => 'Admins',
            self::AUDIENCE_OFFICERS => 'Officers',
        ];
    }

    /**
     * Whether a user may see and generate this report: admins (user type 2)
     * and organization officers/presidents (user type 3 with such a role) are
     * the Reports page's users; the audience narrows which of them.
     */
    public function isAvailableTo(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $isAdmin = (int) $user->user_type === 2;
        $isOfficer = (int) $user->user_type === 3 && Form::isAccessibleBy($user);

        return match ($this->audience ?: self::AUDIENCE_ADMINS) {
            self::AUDIENCE_ALL => $isAdmin || $isOfficer,
            self::AUDIENCE_OFFICERS => $isOfficer,
            default => $isAdmin,
        };
    }

    /**
     * Active reports available to the user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function availableTo(?User $user)
    {
        return static::query()->where('is_active', true)->orderBy('name')->get()
            ->filter(fn (self $report) => $report->isAvailableTo($user))
            ->values();
    }

    protected $fillable = [
        'name',
        'description',
        'icon',
        'audience',
        'definition',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'report_template_id', 'id')->orderBy('slot_order')->orderBy('id');
    }

    public function activeTemplates(): HasMany
    {
        return $this->templates()->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /** @return array<int, array<string,mixed>> */
    public function parameters(): array
    {
        return array_values((array) (($this->definition ?? [])['parameters'] ?? []));
    }

    /** @return array<int, array<string,mixed>> */
    public function tokens(): array
    {
        return array_values((array) (($this->definition ?? [])['tokens'] ?? []));
    }
}
