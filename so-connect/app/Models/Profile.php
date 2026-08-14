<?php

namespace App\Models;

use App\Models\Profile\profileAddress;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected $table = 'profiles';

    protected $primaryKey = 'profile_id';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'contact_number',
        'age',
        'sex',
        'religion',
        'nationality',
        'birthday',
        'course_year',
        'course',
        'year_section',
        'occupation',
        'address',
        'position',
        'photo',
        'birthplace',
        'home_address',
        'parents_guardian',
        'talents_hobbies',
        'financial_support',
        'scholar_provider',
        'financial_support_other',
        'student_id',
        'id_photo_front',
        'id_photo_back',
        'signature_path',
        'origin',
    ];

    /** A profile created by a person signing up or an admin (the normal case). */
    public const ORIGIN_REGISTERED = 'registered';

    /** An auto-created placeholder holding only a captured name + signature. */
    public const ORIGIN_SIGNATURE_ONLY = 'signature_only';

    protected function casts(): array
    {
        return [
            'financial_support' => 'array',
        ];
    }

    /**
     * Only the auto-created signature-only placeholder profiles.
     */
    public function scopeSignatureOnly($query)
    {
        return $query->where('origin', self::ORIGIN_SIGNATURE_ONLY);
    }

    /**
     * Whether this profile was auto-created from a captured signature (as
     * opposed to a real registration).
     */
    public function wasAutoCreated(): bool
    {
        return $this->origin === self::ORIGIN_SIGNATURE_ONLY;
    }

    public function user()
    {
        return $this->hasOne(User::class, 'profile', 'profile_id');
    }

    public function addressOfUser()
    {
        return $this->belongsTo(profileAddress::class, 'address', 'profile_address_id');
    }
}
