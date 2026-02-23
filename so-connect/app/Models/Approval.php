<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    /** @use HasFactory<\Database\Factories\ApprovalFactory> */
    use HasFactory;

    public $primaryKey = 'approval_id';

    protected $fillable = [
        'request', // this is null, for now
        'approver_admin',
    ];

    protected $attributes = [
        'request' => null,
    ];

    public function approver()
    {
        return $this->belongsTo(Administrator::class, 'approver_admin', 'admin_id');
    }

    public function request()
    {
        return $this->belongsTo(ActionRequest::class, 'request', 'request_id');
    }
}
