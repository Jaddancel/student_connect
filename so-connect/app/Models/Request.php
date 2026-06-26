<?php

namespace App\Models;

use App\Http\Resources\ActionRequestResource;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\RequestType;

#[UseResource(ActionRequestResource::class)]
class Request extends Model
{
    use HasFactory;

    protected $table = 'requests';

    protected $primaryKey = 'request_id';

    protected $fillable = [
        'action',
        'requested_at',
        'user',
        'action_type',
        'request_type_id',
        'organization_id',
        'requested_by',
        'payload',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'action_type' => 'int',
            'payload' => 'array',
        ];
    }

    public function approval(): HasOne
    {
        return $this->hasOne(Approval::class, 'request', 'request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user', 'user_id');
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class, 'request_type_id', 'request_type_id');
    }
}
