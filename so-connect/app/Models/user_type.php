<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class user_type extends Model
{
    protected $primaryKey = 'user_type_code';
    protected $table = 'user_type_master';
    protected $fillable = [
        'user_type_name',
    ];
    public $timestamps = false;

    public function users(){
        return $this->hasMany(User::class, 'user_type', 'user_type_code');
    }

}
