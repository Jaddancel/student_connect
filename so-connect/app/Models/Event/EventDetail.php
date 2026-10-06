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

    protected $table = 'event_details';

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

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }
}
