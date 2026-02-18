<?php

namespace App\Models\Member;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class memberOrganization extends Model
{
    /** @use HasFactory<\Database\Factories\Member\memberOrganizationFactory> */
    use HasFactory;

    protected $primaryKey = 'member_organization_id';
    protected $fillable = [
        'member_detail_id',
        'organization_id',
        'member_organization',
    ];

    public $timestamps = false;

   public function member_details(){
        return $this->belongsTo(memberDetail::class, 'member_detail_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }
}
