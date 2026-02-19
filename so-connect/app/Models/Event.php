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
        'approval_id',
        'event_detail'
    ];
    
    public $timestamps = false;
    
    public function approval()
    {
        return $this->belongsTo(Approval::class, 'approval_id', 'approval_id');
    }
}
