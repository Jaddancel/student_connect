<?php

namespace App\Models\Organization;

use App\Models\Member;
use App\Models\Organization;
use Database\Factories\Organization\organizationDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class organizationDetail extends Model
{
    /** @use HasFactory<organizationDetailFactory> */
    use HasFactory;

    protected $primaryKey = 'organization_detail_id';

    protected $table = 'organization_details';

    protected $fillable = [
        'president',
        'organization_name',
        'organization_initials',
    ];

    public function organization()
    {
        return $this->hasOne(Organization::class);
    }

    public function president()
    {
        return $this->hasOne(Member::class, 'member_id', 'president');
    }
}
