<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class President extends Model
{
    /** @use HasFactory<\Database\Factories\PresidentFactory> */
    use HasFactory;
    protected $table = 'presidents';
    protected $primaryKey = 'president_id';
    protected $fillable = [
        'user_id',
        'member_id',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }
    public function organization()
    {
        return $this->hasOne(Organization::class, 'president_id', 'president_id');
    }
}
