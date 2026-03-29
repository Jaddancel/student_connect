<?php

namespace App\Models;

use App\Models\Event\eventDetails;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $primaryKey = 'event_id';

    protected $fillable = ['creator', 'event_detail', 'organization'];

    public function creator()
    {
        return $this->belongsTo(Member::class, 'creator', 'member_id');
    }

    public function details()
    {
        return $this->belongsTo(eventDetails::class, 'event_detail', 'event_detail_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization', 'organization_id');
    }

    public function organizationRelation()
    {
        return $this->belongsTo(Organization::class, 'organization', 'organization_id');
    }
}
