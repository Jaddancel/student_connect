<?php

namespace App\Models\Request;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipRequest extends Model
{
    /** @use HasFactory<\Database\Factories\Request\MembershipRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'action_id',
    ];

    
}
