<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A known signature the verifier compares drawn signatures against. Sourced
 * either from a user profile ('profile') or auto-enrolled under a typed owner
 * name ('enrolled').
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

    public const SOURCE_ENROLLED = 'enrolled';
}
