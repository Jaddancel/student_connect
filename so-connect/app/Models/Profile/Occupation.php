<?php

namespace App\Models\Profile;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Model;

class Occupation extends Model
{
    protected $table = 'occupation_master';

    protected $primaryKey = 'occupation_code';

    protected $fillable = [
        'occupation_name',
    ];

    public $timestamps = false;

    public function profiles()
    {
        return $this->hasMany(Profile::class, 'occupation_code', 'occupation_code');
    }
}
