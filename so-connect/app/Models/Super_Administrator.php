<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Super_Administrator extends Model
{
    /** @use HasFactory<\Database\Factories\SuperAdministratorFactory> */
    use HasFactory;
    protected $table = 'super_administrators';
    protected $primaryKey = 'su_admin_id';
    protected $fillable = [
        'user_id',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }   
}
