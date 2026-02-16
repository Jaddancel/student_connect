<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class organization_type extends Model
{
    use HasFactory;
    protected static string $factory = \Database\Factories\OrganizationTypeFactory::class;
    protected $table = 'organization_type_master';
    protected $primaryKey = 'organization_type_code';
    protected $fillable = [
        'organization_type',
    ];

    public $timestamps = false;
    public function organization()
    {
        return $this->hasMany(Organization::class, 'organization_type_code', 'organization_type_code');
    }
}
