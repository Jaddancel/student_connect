<?php

namespace App\Models;

use App\Models\Organization\organizationDetail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected $primaryKey = 'member_id';

    protected $table = 'members';

    protected $fillable = [
        ['organization', 'approval_id', 'user_id', 'role', 'member_since'],
    ];

    public $timestamps = false;

    public $casts = [
        'member_since' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function organizationDetail()
    {
        return $this->hasOne(organizationDetail::class, 'president', 'member_id');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'evaluation_author', 'member_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'document_author', 'member_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization', 'organization_id');
    }
}
