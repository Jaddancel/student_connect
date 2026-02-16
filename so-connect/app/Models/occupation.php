<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class occupation extends Model
{
    protected $table = 'occupation_master';
    protected $primaryKey = 'occupation_code';
    protected $fillable = ['occupation_name'];

    public $timestamps = false;
    public function profiles(){
        return $this->hasMany(Profile::class, 'occupation_code', 'occupation_code');
    }
}
