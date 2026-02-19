<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperAdministrator extends Model
{
    /** @use HasFactory<\Database\Factories\SuperAdministratorFactory> */
    use HasFactory;

    public $primaryKey = 'su_admin_id';
    protected $fillable = [
        'user',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user', 'user_id');
    }
}