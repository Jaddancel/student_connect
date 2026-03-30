<?php

namespace App\Models\Organization;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;

class OrganizationDetail extends Model
{
    protected $table = 'organization_details';

    protected $primaryKey = 'organization_detail_id';

    protected $fillable = [
        'name',
        'detail_text',
        'initials',
    ];

    public function orgThatThisDetailBelongsTo()
    {
        return $this->belongsTo(Organization::class, 'detail', 'organization_detail_id');
    }
}
