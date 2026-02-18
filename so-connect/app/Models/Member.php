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
        'member_detail',
        'user',
        'role',
        'approval_id'
    ];

    public $timestamps = false;
   public function user(){
        return $this->belongsTo(User::class, 'user', 'user_id');
    }

}
