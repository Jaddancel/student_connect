<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One adviser name in an organization's extensible adviser list (backs the
 * "Advisers" universal field).
 */
class OrganizationAdviser extends Model
{
    protected $table = 'organization_advisers';

    protected $fillable = [
        'organization_id',
        'name',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }
}
