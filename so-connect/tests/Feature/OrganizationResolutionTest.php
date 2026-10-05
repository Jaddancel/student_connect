<?php

use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\OrganizationField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function orgResolutionRole(User $user, int $organizationId, string $role): void
{
    DB::table('organization_officers')->insert([
        'role' => $role,
        'organization' => $organizationId,
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);
}

/** A president of one org who later joined another org as a plain member. */
function presidentWithLaterMembership(): array
{
    $user = recordsUser(3);
    $own = (int) recordsOrganization('Own Org', 'OWN')->getKey();
    $joined = (int) recordsOrganization('Joined Org', 'JND')->getKey();
    orgResolutionRole($user, $own, 'president');
    orgResolutionRole($user, $joined, 'member');

    return [$user, $own, $joined];
}

it('never lets a newer member row outrank an officer role', function () {
    [$user, $own] = presidentWithLaterMembership();

    expect(OrganizationField::resolveOrganization($user)?->getKey())->toBe($own);
});

it('prefers the switcher organization when the user is an officer there', function () {
    $user = recordsUser(3);
    $first = (int) recordsOrganization('First Org', 'FST')->getKey();
    $second = (int) recordsOrganization('Second Org', 'SND')->getKey();
    orgResolutionRole($user, $first, 'officer');
    orgResolutionRole($user, $second, 'president');

    $this->actingAs($user)->withSession(['active_organization_id' => $first]);
    session(['active_organization_id' => $first]);
    expect(OrganizationField::resolveOrganization($user)?->getKey())->toBe($first);

    // A member-only org in the switcher can't be acted for; fall back to the
    // latest officer role.
    $memberOnly = (int) recordsOrganization('Member Org', 'MBR')->getKey();
    orgResolutionRole($user, $memberOnly, 'member');
    session(['active_organization_id' => $memberOnly]);
    expect(OrganizationField::resolveOrganization($user)?->getKey())->toBe($second);
});

it('files a form submission and its request under the officer organization, not a joined one', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    [$user, $own] = presidentWithLaterMembership();

    $form = Form::query()->create([
        'name' => 'Organization Fund Form',
        'route_name' => 'fund-form-'.Str::lower(Str::random(8)),
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>{{note}}</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);
    FormDescription::query()->create([
        'form_id' => $form->id, 'field_key' => 'note', 'field_label' => 'Note',
        'field_type' => 'text', 'is_required' => false, 'field_order' => 1,
    ]);

    $this->actingAs($user)
        ->withSession(['active_organization_id' => $own])
        ->post(route('forms.render.submit', $form->route_name), ['note' => 'Hello'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $submission = FormSubmission::query()->where('form_id', $form->getKey())->sole();
    expect((int) $submission->organization_id)->toBe($own)
        ->and((int) DB::table('requests')->where('form_id', $form->getKey())->value('organization_id'))->toBe($own);
});
