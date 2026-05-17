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
        'status',
        'event_id',
        'request_id',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'persons_responsible' => 'array',
            'status' => 'string',
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
}
