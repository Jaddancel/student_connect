<?php

namespace App\Models\Organization;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;

class organizationType extends Model
{
    protected $table = 'organization_type_master';
    protected $primaryKey = 'organization_type_code';
    protected $keyType = 'unsignedTinyInteger';
    protected $fillable = [
        'organization_type'
    ];

    public $timestamps = false;
    public function organization()
    {
        return $this->hasMany(Organization::class, 'organization_type', 'organization_type_code');
    }

}
