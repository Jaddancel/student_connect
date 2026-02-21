<?php

namespace App\Models;

use App\Models\Member\MemberDetail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    /** @use HasFactory<\Database\Factories\MemberFactory> */
    use HasFactory;

    protected $primaryKey = 'member_id';

    protected $fillable = [
        'member_detail',
        'user',
        'approval_id',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user', 'user_id');
    }

    public function memberDetail()
    {
        return $this->belongsTo(MemberDetail::class, 'member_detail', 'member_detail_id');
    }

    public function approval()
    {
        return $this->belongsTo(Approval::class, 'approval_id', 'approval_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'document_author', 'member_id');
    }
}
