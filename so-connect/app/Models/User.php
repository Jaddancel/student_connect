<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $primaryKey = 'user_id';

    protected $table = 'users';

    protected $attributes = [
        'user_type' => 3,
    ];

    protected $fillable = [
        'user_email',
        'user_password',
        'user_type',
        'profile',
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

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile', 'profile_id');
    }

    public function memberships()
    {
        return $this->hasMany(Member::class, 'user', 'user_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'author', 'user_id');
    }

    public function requests()
    {
        return $this->hasOne(Request::class, 'user', 'user_id');
    }

    public function approvals()
    {
        return $this->belongsTo(Approval::class, 'admin', 'user_id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'generated_for', 'user_id');
    }
}
