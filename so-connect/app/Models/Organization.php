<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    protected $primaryKey = 'organization_id';
    protected $fillable = [
        'president_id',
        'organization_type_code',
        'organization_name',
        'organization_initials'
    ];

    public function members()
    {
        return $this->hasMany(Member::class, 'organization_id', 'organization_id');
    }
}
