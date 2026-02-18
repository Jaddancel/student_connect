<?php

namespace App\Models\Profile;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Occupation extends Model
{
    /** @use HasFactory<\Database\Factories\Profile\OccupationFactory> */
    use HasFactory;
    protected $table = 'occupation_master';
    protected $primaryKey = 'occupation_code';
    protected $fillable = [
        'occupation_name',
    ];

    public function profiles()
    {
        return $this->hasMany(Profile::class, 'occupation_code', 'occupation_code');
    }
}
