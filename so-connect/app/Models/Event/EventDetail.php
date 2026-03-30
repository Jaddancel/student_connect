<?php

namespace App\Models\Event;

use Database\Factories\Event\EventDetailFactory;
use Event;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventDetail extends Model
{
    /** @use HasFactory<EventDetailFactory> */
    use HasFactory;

    protected $table = 'events';

    protected $primaryKey = 'event_detail_id';

    protected $fillable = [
        'name',
        'desc_text',
        'start_time',
        'end_time',
        'location',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_detail', 'event_detail_id');
    }

    public $timestamps = false;

    public function casts()
    {
        return [
            'start_time' => 'dateTime',
            'end_time' => 'dateTime',
            'desc_text' => 'text',
        ];
    }
}
