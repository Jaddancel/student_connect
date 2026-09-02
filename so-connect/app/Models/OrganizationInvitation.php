<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInvitation extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationInvitationFactory> */
    use HasFactory;

    protected $table = 'organization_invitations';

    protected $fillable = [
        'email',
        'token_hash',
        'organization_id',
        'request_id',
        'role',
        'position',
        'expires_at',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'request_id', 'request_id');
    }

    public function hasExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isRedeemed(): bool
    {
        return $this->redeemed_at !== null;
    }
}
