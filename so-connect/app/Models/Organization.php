<?php

namespace App\Models;

use App\Models\Form\formDescription;
use App\Models\Member\memberOrganization;
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
        'organization_initials'
    ];

    public $timestamps = false;

    public function member_organizations()
    {
        return $this->hasMany(memberOrganization::class, 'organization_id', 'organization_id');
    }
    public function forms(){
        return $this->hasMany(formDescription::class, 'form_organizations', 'organization_id');
    }
};
