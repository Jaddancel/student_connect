<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'user_email',
        'user_password',
        'user_type_code',
        'profile_id',
        'user_created_at',
    ];

    public $timestamps = false;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'user_password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'user_password' => 'hashed',
        ];
    }

    public function getAuthPassword()
    {
        return $this->user_password;
    }

    public function userType()
    {
        return $this->belongsTo(UserType::class, 'user_type_code', 'user_type_code');
    }

    public function profile()
    {
        return $this->hasOne(Profile::class, 'profile_id', 'profile_id');
    }

    public function administrator()
    {
        return $this->hasOne(Administrator::class, 'user', 'user_id');
    }

    public function superAdministrator()
    {
        return $this->hasOne(SuperAdministrator::class, 'user', 'user_id');
    }

    public function member()
    {
        return $this->hasMany(Member::class, 'user', 'user_id');
    }
}
