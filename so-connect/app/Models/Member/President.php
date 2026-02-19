<?php

namespace App\Models\Member;

use App\Models\Member;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class President extends Model
{
    /** @use HasFactory<\Database\Factories\Member\PresidentFactory> */
    use HasFactory;

    public $primaryKey = 'president_id';

    protected $fillable = [
        'member',
    ];

    public $timestamps = false;

    public function member()
    {
        return $this->belongsTo(Member::class, 'member');
    }

    public function organization()
    {
        return $this->hasOne(Organization::class, 'president', 'member');
    }
}
