<?php

namespace App\Models\Member;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class memberDetail extends Model
{
    /** @use HasFactory<\Database\Factories\Member\memberDetailFactory> */
    use HasFactory;

    protected $primaryKey = 'member_detail_id';
    protected $fillable = [
        'member_id',
        'member_organization',
        'member_since'
    ];

    protected $timestamps = false; 

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
