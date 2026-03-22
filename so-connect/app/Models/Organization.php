<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $primaryKey = 'organization_id';

    protected $table = 'organizations';

    protected $fillable = [
        'organization_detail',
        'organization_type',
    ];

    public function organizationDetail()
    {
        return $this->belongsTo(Organization\organizationDetail::class, 'organization_detail', 'organization_detail_id');
    }

    public function members()
    {
        return $this->hasMany(Member::class, 'organization', 'organization_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'organization', 'organization_id');
    }
}
