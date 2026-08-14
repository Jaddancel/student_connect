<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A known signature the verifier compares drawn signatures against. Every
 * reference mirrors a profile's signature ('profile'); unrecognized signatures
 * become flagged profiles (see {@see \App\Services\SignatureProfileRegistrar}),
 * which then sync in here — so the registry is uniformly profile-sourced.
 */
class SignatureReference extends Model
{
    protected $table = 'signature_references';

    protected $primaryKey = 'reference_id';

    protected $fillable = [
        'name',
        'signature_path',
        'embedding',
        'source',
        'profile_id',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    public const SOURCE_PROFILE = 'profile';
}
