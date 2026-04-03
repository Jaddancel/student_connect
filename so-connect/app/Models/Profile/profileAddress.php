<?php

namespace App\Models\Profile;

use App\Http\Resources\ProfileAddressResource;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseResource(ProfileAddressResource::class)]
class profileAddress extends Model
{
    /** @use HasFactory<profileAddressFactory> */
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

    public function user()
    {
        return $this->belongsTo(Profile::class, 'address', 'profile_address_id');
    }
}
