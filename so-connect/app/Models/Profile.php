<?php

namespace App\Models;

use App\Models\Profile\Occupation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileFactory> */
    use HasFactory;

    protected $table = 'profiles';
    protected $primaryKey = 'profile_id';

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'occupation_code',
        ];

    public $timestamps = false;

        protected $attributes = [
            'middle_name' => null,
        ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function occupation()
    {
        return $this->belongsTo(Occupation::class, 'occupation_code', 'occupation_code');
    }
        
}
