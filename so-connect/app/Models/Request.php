<?php

namespace App\Models;

use App\Http\Resources\ActionRequestResource;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;

#[UseResource(ActionRequestResource::class)]
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
            'action' => 'string',
            'action_type' => 'int',
        ];
    }

    public function approval()
    {
        return $this->belongsTo(Approval::class, 'request', 'request_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'user', 'user_id');
    }
}
