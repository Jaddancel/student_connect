<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One administrator action (see {@see \App\Services\ActionLogger}). Rows are
 * write-once — no updated_at, created_at set by the logger.
 */
class ActionLog extends Model
{
    protected $table = 'action_logs';

    protected $primaryKey = 'action_log_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'category',
        'action',
        'description',
        'meta',
        'subject_type',
        'subject_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
