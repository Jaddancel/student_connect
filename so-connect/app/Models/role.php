<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;

    protected $table = 'role_master';
    protected $primaryKey = 'role_code';
    public $timestamps = false;
    protected $fillable = [
        'role',
    ];
    
    public function members()
    {
        return $this->hasMany(Member::class, 'role_code', 'role_code');
    }
}
