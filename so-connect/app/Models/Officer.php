<?php

namespace App\Models;

use Database\Factories\OfficerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Officer extends Model
{
    /** @use HasFactory<OfficerFactory> */
    public const CREATED_AT = 'registered_at';

    public const UPDATED_AT = 'reassigned_at';

    use HasFactory;

    protected $table = 'officers';

    protected $primaryKey = 'officer_id';

    protected $fillable = [
        'role',
        'member',
        'yearterm',
    ];

    protected function casts(): array
    {
        return [
            self::CREATED_AT => 'datetime',
            self::UPDATED_AT => 'datetime',
        ];
    }

    public function member()
    {
        return $this->hasMany(Member::class, 'member', 'member_id');
    }

    public function organizationOfOfficer()
    {
        return $this->belongsToMany(Organization::class, 'officer', 'officer_id');
    }

    public function yearTerm()
    {
        return $this->hasOne(YearTerm::class, 'yearterm', 'year_term_code');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'author', 'officer_id');
    }

    public function createdEvents()
    {
        return $this->hasMany(Event::class, 'author', 'officer_id');
    }
}
