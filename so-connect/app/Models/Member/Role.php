<?php

namespace App\Models\Member;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{

    public $table = 'role_master';
    protected $primaryKey = 'role_code';
    protected $fillable = ['role_name'];
    public function members()
    {
        return $this->hasMany(Member::class, 'role', 'role_code');
    }
}
