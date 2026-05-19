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

    protected $table = 'organization_officers';

    protected $primaryKey = 'org_officer_id';

    protected $fillable = [
        'role',
        'organization',
        'user',
        'approval',
        'member_since',
        'yearterm',
    ];

    protected function casts(): array
    {
        return [
            self::CREATED_AT => 'datetime',
            self::UPDATED_AT => 'datetime',
            'member_since' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user', 'user_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization', 'organization_id');
    }

    public function approval()
    {
        return $this->belongsTo(Approval::class, 'approval', 'approval_id');
    }

    public function yearTerm()
    {
        return $this->hasOne(YearTerm::class, 'yearterm', 'year_term_code');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'author', 'org_officer_id');
    }

    public function createdEvents()
    {
        return $this->hasMany(Event::class, 'author', 'officer_id');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class, 'admin', 'org_officer_id');
    }
}
