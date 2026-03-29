<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    protected $table = 'requests';

    protected $primaryKey = 'request_id';

    protected $fillable = [
        'action',
        'requested_at',
        'user',
        'action_type',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
        ];
    }

    public function approval()
    {
        return $this->belongsTo(Approval::class, 'request', 'request_id');
    }

    public function requester()
    {
        return $this->hasOne(User::class, 'user', 'user_id');
    }
}
