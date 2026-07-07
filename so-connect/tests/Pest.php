<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a user (with profile) of the given user_type for records/admin tests.
 */
function recordsUser(int $type, array $profileAttributes = []): \App\Models\User
{
    $profile = \App\Models\Profile::query()->create(array_merge([
        'first_name' => 'Rec',
        'last_name' => 'User'.\Illuminate\Support\Str::random(6),
        'middle_name' => 'T',
        'occupation' => 'Staff',
    ], $profileAttributes));

    return \App\Models\User::query()->create([
        'user_email' => 'rec'.\Illuminate\Support\Str::random(8).'@example.com',
        'user_password' => 'password',
        'user_type' => $type,
        'profile' => $profile->getKey(),
    ]);
}

/**
 * Create an organization with a detail name for records tests.
 */
function recordsOrganization(string $name, ?string $initials = null): \App\Models\Organization
{
    $detail = \App\Models\Organization\OrganizationDetail::query()->create([
        'name' => $name,
        'initials' => $initials,
        'detail_text' => $name.' description',
    ]);

    return \App\Models\Organization::query()->create([
        'detail' => $detail->getKey(),
        'organization_type' => 1,
    ]);
}
