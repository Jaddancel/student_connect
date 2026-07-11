<?php

use App\Models\Profile;
use App\Support\UniversalField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes a catalog keyed by canonical universal keys', function () {
    $keys = UniversalField::keys();

    expect($keys)->toContain('first_name', 'religion', 'course', 'year_section', 'home_address')
        ->and(UniversalField::has('first_name'))->toBeTrue()
        ->and(UniversalField::has('not_a_field'))->toBeFalse()
        ->and(UniversalField::get('first_name')['profile_column'])->toBe('first_name')
        ->and(UniversalField::get('not_a_field'))->toBeNull()
        ->and(UniversalField::label('home_address'))->toBe('Home Address');
});

it('no longer exposes the removed universal fields', function () {
    $keys = UniversalField::keys();

    expect($keys)->not->toContain('contact_number', 'age', 'nationality', 'birthday', 'course_year', 'student_id', 'id_photo_front', 'photo', 'address');
});

it('separates profile-source from org-source keys', function () {
    expect(UniversalField::keysBySource('org'))
        ->toEqualCanonicalizing(['adviser', 'org_president', 'org_auditor', 'org_secretary'])
        ->and(UniversalField::keysBySource('profile'))->not->toContain('adviser', 'org_president')
        ->and(UniversalField::isOrgField('org_president'))->toBeTrue()
        ->and(UniversalField::isOrgField('first_name'))->toBeFalse();
});

it('groups the catalog for palette rendering', function () {
    $grouped = UniversalField::grouped();

    expect($grouped)->toHaveKeys(['name', 'contact', 'academic', 'personal', 'organization'])
        ->and($grouped['organization'])->toHaveKey('adviser')
        ->and(UniversalField::groupedBySource('profile'))->not->toHaveKey('organization');
});

it('reads a scalar universal value off a profile', function () {
    $profile = new Profile([
        'first_name' => 'Juan',
        'religion' => 'Roman Catholic',
        'course' => 'BSIT',
    ]);

    expect(UniversalField::valueFor($profile, 'first_name'))->toBe('Juan')
        ->and(UniversalField::valueFor($profile, 'religion'))->toBe('Roman Catholic')
        ->and(UniversalField::valueFor($profile, 'course'))->toBe('BSIT');
});

it('returns null for missing profile, unknown key, empty value, or an org-source key', function () {
    $profile = new Profile(['year_section' => '', 'first_name' => 'Ana']);

    expect(UniversalField::valueFor(null, 'first_name'))->toBeNull()
        ->and(UniversalField::valueFor($profile, 'not_a_field'))->toBeNull()
        ->and(UniversalField::valueFor($profile, 'year_section'))->toBeNull()
        // Org-source keys never resolve off a profile (use OrganizationField).
        ->and(UniversalField::valueFor($profile, 'org_president'))->toBeNull();
});
