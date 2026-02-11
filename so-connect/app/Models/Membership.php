<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model

{
    use HasFactory;
    protected $primaryKey = 'membership_id';
    protected $fillable = [
        'user_id',
        'organization_id',
        'approval_id',
        'role_code'
    ];

public function user(){
    return $this->belongsTo(User::class);
}

public function organization(){
    return $this->belongsTo(Organization::class);
}

public function approval(){
    return $this->belongsTo(Approval::class);
}

public function requests(){
    return $this->morphMany(Request::class, 'action');  
}

}