<?php

namespace App\Models\Organization;

use App\Models\Organization;
use Database\Factories\Organization\organizationDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationDetail extends Model
{
    use HasFactory;

    protected $table = 'organization_details';

    protected $primaryKey = 'organization_detail_id';

    protected $fillable = [
        'name',
        'detail_text',
        'initials',
    ];

    protected static function newFactory(): organizationDetailFactory
    {
        return organizationDetailFactory::new();
    }

    public function orgThatThisDetailBelongsTo()
    {
        return $this->belongsTo(Organization::class, 'detail', 'organization_detail_id');
    }
}
