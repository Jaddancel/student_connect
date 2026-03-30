<?php

namespace App\Models;

use App\Models\Event\EventDetail;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $table = 'events';

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'organization',
        'creator',
        'event_detail',
    ];

    public function detailOfEvent()
    {
        return $this->hasOne(EventDetail::class, 'event_detail', 'event_detail_id');
    }

    public function organizationOfEvent()
    {
        return $this->belongsTo(Organization::class, 'organization', 'organization_id');
    }

    public function creator()
    {
        return $this->belongsTo(Officer::class, 'author', 'officer_id');
    }
}
