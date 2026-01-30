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
        'president_id'
    ];

    public function organization(){
        return $this->belongsTo(Organization::class, 'president_id', 'organization_president_id');
    }

    public function approvals(){
        return $this->hasMany(Approval::class, 'president_id', 'organization_president_id');
    }

}
