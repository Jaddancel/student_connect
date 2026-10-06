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
        'accreditation_status',
        'accreditation_disabled_at',
    ];

    protected $primaryKey = 'organization_id';

    protected function casts(): array
    {
        return [
            'accreditation_disabled_at' => 'datetime',
        ];
    }

    /**
     * True when the org has been disabled for missing its accreditation
     * (posts hidden, members blocked, org hidden until restored or purged).
     */
    public function isAccreditationDisabled(): bool
    {
        return $this->accreditation_status === \App\Services\AccreditationService::STATUS_DISABLED;
    }

    /** Scope to orgs that are NOT accreditation-disabled. */
    public function scopeAccreditationActive($query)
    {
        return $query->where('accreditation_status', '!=', \App\Services\AccreditationService::STATUS_DISABLED);
    }

    public function officersOfThisOrganization()
    {
        return $this->hasMany(Officer::class, 'organization', 'organization_id');
    }

    public function advisers()
    {
        return $this->hasMany(OrganizationAdviser::class, 'organization_id', 'organization_id');
    }

    public function detail()
    {
        return $this->belongsTo(OrganizationDetail::class, 'detail', 'organization_detail_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'organization', 'organization_id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'organization', 'organization_id');
    }
}
