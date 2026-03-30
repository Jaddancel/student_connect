<?php

namespace App\Models;

use App\Models\Organization\OrganizationDetail;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $table = 'organizations';

    protected $fillable = [
        'detail',
        'organization_type',
        'officer',
    ];

    public function officer()
    {
        return $this->hasMany(Officer::class, 'officer', 'officer_id');
    }

    public function detail()
    {
        return $this->hasOne(OrganizationDetail::class, 'detail', 'organization_detail_id');
    }

    public function memberOfThisOrganization()
    {
        return $this->belongsTo(Member::class, 'organization', 'organization_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'organization', 'organization_id');
    }
}
