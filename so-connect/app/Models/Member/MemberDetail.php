<?php

namespace App\Models\Member;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberDetail extends Model
{
    /** @use HasFactory<\Database\Factories\Member\MemberDetailFactory> */
    use HasFactory;

    protected $primaryKey = 'member_detail_id';

    protected $fillable = [
        'member_organization',
        'role',
        'member_since',
    ];

    public $timestamps = false;

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_detail', 'member_detail_id');
    }

    public function role()
    {
        return $this->hasOne(Role::class, 'role', 'role_code');
    }

    public function organization()
    {
        return $this->belongsTo(MemberOrganization::class, 'member_organization', 'member_organization_id');
    }
}
