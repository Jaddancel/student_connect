<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    /** @use HasFactory<\Database\Factories\MemberFactory> */
    use HasFactory;

    protected $primaryKey = 'member_id';
    protected $fillable = [
        'user_id',
        'approval_id',
        'role_code',
        'organization_id',
    ];


// TODO: organization model too. - Jad

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

}
