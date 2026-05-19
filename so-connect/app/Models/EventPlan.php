<?php

namespace App\Models;

use Database\Factories\EventPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPlan extends Model
{
    /** @use HasFactory<EventPlanFactory> */
    use HasFactory;

    protected $table = 'event_plans';

    protected $primaryKey = 'event_plan_id';

    protected $fillable = [
        'organization_id',
        'created_by',
        'title',
        'target_date',
        'resources_needed',
        'persons_responsible',
        'event_location',
        'event_start_time',
        'event_end_time',
        'event_description',
        'status',
        'event_id',
        'request_id',
        'parent_plan_id',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'persons_responsible' => 'array',
            'status' => 'string',
            'event_start_time' => 'datetime',
            'event_end_time' => 'datetime',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'request_id', 'request_id');
    }

    public function parentPlan(): BelongsTo
    {
        return $this->belongsTo(EventPlan::class, 'parent_plan_id', 'event_plan_id');
    }

    public function isEventRequest(): bool
    {
        return $this->parent_plan_id !== null;
    }
}
