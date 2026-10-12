<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Profile\profileAddress;
use App\Models\User;
use App\Support\AddressParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountCreationService
{
    public function create(array $data, int $userType, ?string $photoPath, ?string $signaturePath): User
    {
        return DB::transaction(function () use ($data, $userType, $photoPath, $signaturePath) {
            $homeAddress = trim((string) ($data['home_address'] ?? ''));

            $address = profileAddress::create(
                AddressParser::parse($homeAddress !== '' ? $homeAddress : ($data['present_address'] ?? ''))
            );

            $profile = Profile::create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? '',
                'last_name' => $data['last_name'],
                'contact_number' => $data['contact_number'],
                'age' => $data['age'] ?? null,
                'sex' => ! empty($data['sex']) ? $data['sex'] : null,
                'religion' => $data['religious_affiliation'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'birthday' => $data['birthday'] ?? null,
                'birthplace' => $data['birthplace'] ?? null,
                'course_year' => trim(($data['course'] ?? '').' '.($data['year_level'] ?? '')),
                'course' => $data['course'] ?? '',
                'year_section' => $data['year_level'] ?? '',
                'occupation' => $userType === User::TYPE_SUPERADMIN ? 'SuperAdmin' : 'Administrator',
                'address' => (int) $address->profile_address_id,
                'home_address' => $homeAddress !== '' ? $homeAddress : null,
                'photo' => $photoPath ?? '',
                'signature_path' => $signaturePath,
            ]);

            if ($profile->signature_path) {
                app(SignatureReferenceService::class)->syncFromProfile($profile->fresh());
            }

            return User::create([
                'user_email' => $data['email'],
                'google_id' => $data['google_id'] ?? null,
                'email_verified_at' => ! empty($data['google_id']) ? now() : null,
                'user_password' => Hash::make('tAU100!!'),
                'user_type' => $userType,
                'profile' => (int) $profile->profile_id,
                'profile_pending' => false,
                'force_password_change' => true,
            ]);
        });
    }
}
