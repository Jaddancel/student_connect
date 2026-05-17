<?php

namespace App\Models;

use App\Models\Profile\profileAddress;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected $table = 'profiles';

    protected $primaryKey = 'profile_id';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'contact_number',
        'age',
        'sex',
        'religion',
        'nationality',
        'birthday',
        'course_year',
        'occupation',
        'address',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'profile', 'profile_id');
    }

    public function addressOfUser()
    {
        return $this->belongsTo(profileAddress::class, 'address', 'profile_address_id');
    }
}
