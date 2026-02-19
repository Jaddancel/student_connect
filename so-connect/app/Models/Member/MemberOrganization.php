<?php

namespace App\Models\Member;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberOrganization extends Model
{
    /** @use HasFactory<\Database\Factories\Member\MemberOrganizationFactory> */
    use HasFactory;

    protected $primaryKey = 'member_organization_id';

    protected $fillable = [
        'organization_id',
    ];

    public $timestamps = false;

    public function member_details()
    {
        return $this->belongsTo(MemberDetail::class, 'member_organization', 'member_organization_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }
}
