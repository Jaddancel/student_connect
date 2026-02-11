<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    /** @use HasFactory<\Database\Factories\RequestFactory> */
    use HasFactory;

    protected $fillable = [
        'request_id',
        'action_id', // id of the yet unapproved item
        'action_type' // type of the yet unapproved item (e.g. membership, organization, event)
    ];

}
