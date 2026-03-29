<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $primaryKey = 'approval_id';

    public $timestamps = false;

    protected $fillable = ['admin', 'approval_timestamp', 'request', 'decision'];

    public $casts = [
        'approval_timestamp' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request', 'request_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin', 'user_id');
    }
}
