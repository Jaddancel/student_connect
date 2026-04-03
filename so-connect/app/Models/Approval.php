<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    protected $table = 'approvals';

    protected $fillable = [
        'approved_at',
        'request',
        'admin',
        'is_rejected',
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

    public function membershipApproval()
    {
        return $this->belongsTo(Member::class, 'approval', 'approval_id');
    }
}
