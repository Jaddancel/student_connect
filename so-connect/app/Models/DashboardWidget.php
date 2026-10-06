<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    use HasFactory;

    public const ROLE_MEMBER = 'member';
    public const ROLE_OFFICER = 'officer';
    public const ROLE_PRESIDENT = 'president';
    public const ROLE_ADMIN = 'admin';

    public const ROLE_OPTIONS = [
        self::ROLE_MEMBER,
        self::ROLE_OFFICER,
        self::ROLE_PRESIDENT,
        self::ROLE_ADMIN,
    ];

    public const TYPE_APPROVAL_BREAKDOWN = 'approval_breakdown';
    public const TYPE_REQUEST_VOLUME = 'request_volume';
    public const TYPE_REQUEST_LIST = 'request_list';
    public const TYPE_ROLE_STATS = 'role_stats';

    public const TYPE_OPTIONS = [
        self::TYPE_APPROVAL_BREAKDOWN,
        self::TYPE_REQUEST_VOLUME,
        self::TYPE_REQUEST_LIST,
        self::TYPE_ROLE_STATS,
    ];

    protected $table = 'dashboard_widgets';

    protected $primaryKey = 'dashboard_widget_id';

    protected $fillable = [
        'role',
        'widget_type',
        'title',
        'config',
        'sort_order',
        'column_span',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'sort_order' => 'integer',
            'column_span' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}
