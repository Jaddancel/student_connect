<?php

namespace App\Models\Profile;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileAddress extends Model // <-- Capital P here!
{
    /** @use HasFactory<ProfileAddressFactory> */
    use HasFactory;

    protected $table = 'profile_addresses';

    protected $primaryKey = 'profile_address_id';

    protected $fillable = [
        'country',
        'province',
        'town',
        'barangay',
    ];

    public $timestamps = false;

    // Pro-tip: Since this links to the Profile model, it's best practice to name the method 'profile' instead of 'user'
    public function profile()
    {
        return $this->belongsTo(Profile::class, 'address', 'profile_address_id');
    }
}
