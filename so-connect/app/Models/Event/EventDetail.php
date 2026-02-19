<?php

namespace App\Models\Event;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Event;

class EventDetail extends Model
{
    /** @use HasFactory<\Database\Factories\Event\EventDetailFactory> */
    use HasFactory;

    protected $primaryKey = 'event_detail_id';
    protected $fillable = [
        'event_name',
        'event_description_text',
        'event_created_at',
        'event_start_date',
        'event_end_date',
        'event_location',
    ];

    protected $casts = [
        'event_created_at' => 'datetime',
        'event_start_date' => 'datetime',
        'event_end_date' => 'datetime',
    ];

    protected $attributes = [
        'event_location' => null
    ];

    public $timestamps = false;

    public function event()
    {
        return $this->hasOne(Event::class, 'event_detail', 'event_detail_id');
    }

}