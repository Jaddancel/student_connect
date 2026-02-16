<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileFactory> */
    use HasFactory;

    protected $primaryKey = 'profile_id';
    
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'occupation_code',
    ];
}
