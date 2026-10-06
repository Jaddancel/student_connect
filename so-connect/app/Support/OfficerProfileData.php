<?php

namespace App\Support;

use App\Models\Profile\profileAddress;

/**
 * Maps a sign-up request payload onto {@see \App\Models\Profile} attributes.
 *
 * The mapping lives here because two paths build the same profile: a sign-up
 * creates the applicant's pending account up front, while an admin approving a
 * legacy request (filed before pending accounts existed) still creates the
 * account at approval time.
 */
class OfficerProfileData
{
    /**
     * Columns the profiles table declares NOT NULL — always written, even empty.
     * Everything else is dropped when the form did not collect it: `sex` is an
     * ENUM and `birthday` a DATE, and writing '' into either is an error under
     * strict mode rather than the "leave it blank" the empty value meant.
     */
    private const ALWAYS_WRITTEN = ['first_name', 'middle_name', 'last_name', 'occupation'];

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>  Profile::create()/update() attributes
     */
    public static function fromPayload(array $payload, ?int $addressId = null): array
    {
        $courseYear = trim(trim((string) ($payload['course'] ?? '')).' - '.trim((string) ($payload['year_level'] ?? '')), " -");

        $attributes = [
            'first_name' => (string) ($payload['first_name'] ?? ''),
            'middle_name' => (string) ($payload['middle_name'] ?? ''),
            'last_name' => (string) ($payload['last_name'] ?? ''),
            'contact_number' => (string) ($payload['contact_number'] ?? ''),
            'age' => (int) ($payload['age'] ?? 0),
            'sex' => (string) ($payload['sex'] ?? ''),
            'religion' => (string) ($payload['religious_affiliation'] ?? ''),
            'nationality' => (string) ($payload['nationality'] ?? ''),
            'birthday' => (string) ($payload['birthday'] ?? ''),
            'birthplace' => (string) ($payload['birthplace'] ?? ''),
            'course_year' => $courseYear,
            'occupation' => 'Student',
            'position' => (string) ($payload['position'] ?? ''),
            'photo' => (string) ($payload['photo'] ?? ''),
            'home_address' => (string) ($payload['home_address'] ?? ''),
            'parents_guardian' => (string) ($payload['parents_guardian'] ?? ''),
            'talents_hobbies' => (string) ($payload['talents_hobbies'] ?? ''),
            'financial_support' => $payload['financial_support'] ?? [],
            'scholar_provider' => (string) ($payload['scholar_provider'] ?? ''),
            'financial_support_other' => (string) ($payload['others_specify'] ?? ''),
            'student_id' => (string) ($payload['student_id'] ?? ''),
            'id_photo_front' => (string) ($payload['id_photo_front'] ?? ''),
            'id_photo_back' => (string) ($payload['id_photo_back'] ?? ''),
            'signature_path' => (string) ($payload['signature'] ?? ''),
        ];

        $attributes = array_filter(
            $attributes,
            fn ($value, string $column) => in_array($column, self::ALWAYS_WRITTEN, true)
                || ($value !== '' && $value !== null && $value !== []),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($addressId !== null) {
            $attributes['address'] = $addressId;
        }

        return $attributes;
    }

    /**
     * The address row a profile points at. Sign-up collects one free-text
     * present address, which the schema keeps as the barangay line.
     *
     * @param  array<string,mixed>  $payload
     */
    public static function createAddress(array $payload): profileAddress
    {
        return profileAddress::create([
            'country' => 'Philippines',
            'province' => '',
            'town' => '',
            'barangay' => (string) ($payload['present_address'] ?? ''),
        ]);
    }
}
