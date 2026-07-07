<?php

use App\Models\Profile;
use App\Models\Profile\profileAddress;
use App\Support\UniversalField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes a catalog keyed by canonical universal keys', function () {
    $keys = UniversalField::keys();

    expect($keys)->toContain('first_name', 'religion', 'course_year', 'student_id', 'id_photo_front')
        ->and(UniversalField::has('first_name'))->toBeTrue()
        ->and(UniversalField::has('not_a_field'))->toBeFalse()
        ->and(UniversalField::get('first_name')['profile_column'])->toBe('first_name')
        ->and(UniversalField::get('not_a_field'))->toBeNull()
        ->and(UniversalField::label('student_id'))->toBe('Student ID');
});

it('groups the catalog for palette rendering', function () {
    $grouped = UniversalField::grouped();

    expect($grouped)->toHaveKeys(['name', 'contact', 'personal', 'academic', 'id'])
        ->and($grouped['id'])->toHaveKey('student_id');
});

it('reads a scalar universal value off a profile', function () {
    $profile = new Profile([
        'first_name' => 'Juan',
        'religion' => 'Roman Catholic',
        'student_id' => '21-1234-567',
    ]);

    expect(UniversalField::valueFor($profile, 'first_name'))->toBe('Juan')
        ->and(UniversalField::valueFor($profile, 'religion'))->toBe('Roman Catholic')
        ->and(UniversalField::valueFor($profile, 'student_id'))->toBe('21-1234-567');
});

it('returns null for missing profile, unknown key, or empty value', function () {
    $profile = new Profile(['nationality' => '']);

    expect(UniversalField::valueFor(null, 'first_name'))->toBeNull()
        ->and(UniversalField::valueFor($profile, 'not_a_field'))->toBeNull()
        ->and(UniversalField::valueFor($profile, 'nationality'))->toBeNull()
        ->and(UniversalField::valueFor($profile, 'religion'))->toBeNull();
});

it('formats the present address from the related profile_addresses row', function () {
    $address = profileAddress::factory()->create([
        'barangay' => 'San Roque',
        'town' => 'Legazpi',
        'province' => 'Albay',
        'country' => 'Philippines',
    ]);

    $profile = Profile::query()->create([
        'first_name' => 'Ana',
        'middle_name' => 'B',
        'last_name' => 'Cruz',
        'occupation' => 'Student',
        'address' => $address->getKey(),
    ]);

    expect(UniversalField::valueFor($profile->fresh(), 'address'))
        ->toBe('San Roque, Legazpi, Albay, Philippines');
});

it('returns null present address when the profile has no linked address', function () {
    $profile = Profile::query()->create([
        'first_name' => 'No',
        'middle_name' => 'X',
        'last_name' => 'Address',
        'occupation' => 'Student',
    ]);

    expect(UniversalField::valueFor($profile->fresh(), 'address'))->toBeNull();
});
