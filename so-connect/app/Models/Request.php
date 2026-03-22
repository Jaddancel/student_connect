<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    protected $primaryKey = 'request_id';

    protected $table = 'requests';

    public $timestamps = false;

    protected $fillable = ['action', 'request_made_at', 'action_type'];

    public $casts = [
        'request_made_at' => 'datetime',
    ];

    public function approval()
    {
        return $this->hasOne(Approval::class, 'request', 'request_id');
    }
}
