<?php

namespace App\Models\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class userType extends Model
{
    public $table = 'user_type_master';
    public $primaryKey = 'user_type_code';
    protected $fillable = [
        'user_type',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'user_type_code', 'user_type_code');
    }

}
