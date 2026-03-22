<?php

namespace App\Models\Event;

use App\Models\Event;
use Database\Factories\Event\eventDetailsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class eventDetails extends Model
{
    /** @use HasFactory<eventDetailsFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'event_detail_id';

    protected $fillable = ['event_name', 'event_desc_text', 'event_location', 'event_start_time', 'event_end_time'];

    protected $casts = [
        'event_start_time' => 'datetime',
        'event_end_time' => 'datetime',
    ];

    public function event()
    {
        return $this->hasOne(Event::class, 'event_detail', 'event_detail_id');
    }
}
