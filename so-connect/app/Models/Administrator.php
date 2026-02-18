<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Administrator extends Model
{
    /** @use HasFactory<\Database\Factories\AdministratorFactory> */
    use HasFactory;

    protected $fillable = [
        'user',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user', 'user_id');
    }
}
