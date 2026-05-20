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
        'purpose_of_activity',
        'time_of_activity',
        'place_venue',
        'university_facilities',
        'president_name',
        'president_contact',
        'faculty_advisers',
        'college_dean',
        'activity_types',
        'activity_types_other',
        'seminar_level',
        'area_scope',
        'area_scope_other',
        'sponsor',
        'sponsor_other',
        'cosponsor_count',
        'extension_services',
        'related_to_organization',
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
            'purpose_of_activity' => 'string',
            'time_of_activity' => 'string',
            'place_venue' => 'string',
            'university_facilities' => 'array',
            'president_name' => 'string',
            'president_contact' => 'string',
            'faculty_advisers' => 'array',
            'college_dean' => 'string',
            'activity_types' => 'array',
            'activity_types_other' => 'string',
            'area_scope' => 'string',
            'area_scope_other' => 'string',
            'sponsor' => 'string',
            'sponsor_other' => 'string',
            'extension_services' => 'boolean',
            'related_to_organization' => 'boolean',
            'cosponsor_count' => 'integer',
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
