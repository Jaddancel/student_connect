<?php

namespace App\Models;

use Database\Factories\MembersFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    /** @use HasFactory<MembersFactory> */
    use HasFactory;

    protected $table = 'members';

    protected $primaryKey = 'member_id';

    protected $fillable = [
        'organization',
        'approval',
        'user',
        'member_since',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user', 'user_id');
    }

    public function organization()
    {
        return $this->hasMany(Organization::class, 'organization', 'organization_id');
    }

    public function approval()
    {
        return $this->hasMany(Approval::class, 'approval', 'approval_id');
    }

    protected function casts(): array
    {
        return [
            'member_since' => 'datetime',
        ];
    }
}
