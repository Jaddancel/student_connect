<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected $table = 'profile';

    protected $primaryKey = 'profile_id';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'occupation',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'profile', 'profile_id');
    }
}
