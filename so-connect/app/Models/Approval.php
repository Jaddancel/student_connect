<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    protected $table = 'approvals';

    protected $primaryKey = 'approval_id';

    protected $fillable = [
        'approved_at',
        'request',
        'admin',
        'stage',
        'is_rejected',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'is_rejected' => 'boolean',
        ];
    }

    public $timestamps = false;

    public function adminThatApproved()
    {
        return $this->belongsTo(Officer::class, 'admin', 'org_officer_id');
    }

    public function officerApproval()
    {
        return $this->hasOne(Officer::class, 'approval', 'approval_id');
    }
}
