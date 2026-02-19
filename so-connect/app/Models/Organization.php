<?php

namespace App\Models;

use App\Models\Form\FormDescription;
use App\Models\Member\MemberOrganization;
use App\Models\Organization\OrganizationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    protected $table = 'organizations';

    protected $primaryKey = 'organization_id';

    protected $fillable = [
        'organization_name',
        'president',
        'organization_type',
        'organization_initials',
    ];

    public $timestamps = false;

    public function member_organizations()
    {
        return $this->hasMany(MemberOrganization::class, 'organization_id', 'organization_id');
    }

    public function forms()
    {
        return $this->hasMany(FormDescription::class, 'form_organizations', 'organization_id');
    }

    public function organization_type()
    {
        return $this->belongsTo(OrganizationType::class, 'organization_type', 'organization_type_id');
    }

    public function president()
    {
        return $this->belongsTo(Member::class, 'president', 'member_id');
    }
}
