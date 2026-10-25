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

// Asserted one key at a time: a negated multi-argument `toContain` passes as soon as
// *any* one needle is absent, so the grouped form silently allowed a retired key back
// into the catalog. Retired keys still stamped on a form make it unsaveable, because
// the builder validates universal_key with Rule::in(UniversalField::keys()).
it('no longer exposes the removed universal fields', function (string $retired) {
    expect(UniversalField::keys())->not->toContain($retired)
        ->and(UniversalField::has($retired))->toBeFalse();
})->with(['contact_number', 'age', 'nationality', 'course_year', 'student_id', 'id_photo_front', 'photo', 'address']);

it('exposes birthday as a personal profile field', function () {
    expect(UniversalField::keys())->toContain('birthday')
        ->and(UniversalField::keysBySource('profile'))->toContain('birthday')
        ->and(UniversalField::get('birthday')['group'])->toBe('personal')
        ->and(UniversalField::get('birthday')['profile_column'])->toBe('birthday');
});

it('formats a birthday as "Month Day, Year"', function () {
    $profile = new Profile(['birthday' => '2000-01-05']);

    expect(UniversalField::valueFor($profile, 'birthday'))->toBe('January 5, 2000');
});

it('leaves an unparseable birthday untouched and null when empty', function () {
    expect(UniversalField::valueFor(new Profile(['birthday' => 'not-a-date']), 'birthday'))->toBe('not-a-date')
        ->and(UniversalField::valueFor(new Profile(['birthday' => '']), 'birthday'))->toBeNull();
});

it('separates profile-source from org-source keys', function () {
    expect(UniversalField::keysBySource('org'))
        ->toEqualCanonicalizing([
            'org_name', 'adviser', 'adviser_2', 'advisers', 'org_president', 'org_treasurer', 'org_auditor', 'org_secretary', 'org_category',
            'org_president_signature', 'org_treasurer_signature', 'org_auditor_signature', 'org_secretary_signature',
            'org_president_contact', 'org_treasurer_contact', 'org_auditor_contact', 'org_secretary_contact',
        ])
        ->and(UniversalField::keysBySource('profile'))->not->toContain('adviser', 'org_president')
        ->and(UniversalField::isOrgField('org_president'))->toBeTrue()
        ->and(UniversalField::isOrgField('org_name'))->toBeTrue()
        ->and(UniversalField::isOrgField('first_name'))->toBeFalse();
});

it('resolves the organization name org field', function () {
    $detailId = \Illuminate\Support\Facades\DB::table('organization_details')->insertGetId([
        'name' => 'Society of Programmers',
        'detail_text' => 'A student programming organization.',
        'initials' => 'SOP',
    ]);
    $orgId = \Illuminate\Support\Facades\DB::table('organizations')->insertGetId([
        'detail' => $detailId,
        'organization_type' => 1,
    ]);
    $organization = \App\Models\Organization::query()->findOrFail($orgId);

    expect(\App\Support\OrganizationField::value($organization, 'org_name'))->toBe('Society of Programmers')
        ->and(\App\Support\OrganizationField::value(null, 'org_name'))->toBeNull();
});

it('resolves the primary, second, and complete adviser values in registration order', function () {
    $detailId = \Illuminate\Support\Facades\DB::table('organization_details')->insertGetId([
        'name' => 'Adviser Data Organization',
        'detail_text' => 'Tests stored adviser values.',
        'initials' => 'ADO',
    ]);
    $organization = \App\Models\Organization::query()->create([
        'detail' => $detailId,
        'organization_type' => 1,
    ]);
    $organization->advisers()->createMany([
        ['name' => 'Dr. Elena Santos'],
        ['name' => 'Prof. Marco Reyes'],
    ]);

    expect(\App\Support\OrganizationField::value($organization, 'adviser'))->toBe('Dr. Elena Santos')
        ->and(\App\Support\OrganizationField::value($organization, 'adviser_2'))->toBe('Prof. Marco Reyes')
        ->and(\App\Support\OrganizationField::value($organization, 'advisers'))
        ->toBe(['Dr. Elena Santos', 'Prof. Marco Reyes']);

    $request = \Illuminate\Http\Request::create('/forms/example');
    $fields = collect([
        new \App\Models\Form\FormDescription([
            'field_key' => 'adviser_name',
            'field_type' => \App\Forms\FieldType::TEXT,
            'universal_key' => 'adviser',
        ]),
        new \App\Models\Form\FormDescription([
            'field_key' => 'adviser_names',
            'field_type' => \App\Forms\FieldType::TEXT_LIST,
            'universal_key' => 'advisers',
        ]),
    ]);

    expect(\App\Forms\FormRenderContext::profilePrefill($request, $fields, $organization))->toBe([
        'adviser_name' => 'Dr. Elena Santos',
        'adviser_names' => ['Dr. Elena Santos', 'Prof. Marco Reyes'],
    ]);
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

// The seeder once stamped `contact_number` — retired from the catalog — onto the
// Directory of Student Officers, which made that form impossible to save: the builder
// validates every field with Rule::in(UniversalField::keys()) and rejected the key the
// seeder itself had written.
it('seeds no form field with a universal key outside the catalog', function () {
    $this->seed(\Database\Seeders\FormPagesSeeder::class);

    $stale = \App\Models\Form\FormDescription::query()
        ->whereNotNull('universal_key')
        ->where('universal_key', '<>', '')
        ->whereNotIn('universal_key', UniversalField::keys())
        ->pluck('universal_key', 'field_key');

    expect($stale)->toBeEmpty();
});
