<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    /** @use HasFactory<\Database\Factories\UserTypeFactory> */
    use HasFactory;

    public $table = 'user_type_master';
    public $primaryKey = 'user_type_code';
    public $timestamps = false;
    protected $fillable = [
        'user_type',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'user_type_code', 'user_type_code');
    }
}