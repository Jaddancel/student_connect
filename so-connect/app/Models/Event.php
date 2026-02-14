<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    protected $primaryKey = 'event_id';
    protected $fillable = [
        'event_name',
        'event_description',
        'event_start_time',
        'event_end_time',
        'membership_id',
        'approval_id',
    ];

}
