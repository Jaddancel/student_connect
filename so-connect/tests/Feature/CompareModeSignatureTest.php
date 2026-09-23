<?php

use App\Forms\ExpectedSignatories;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\Profile;
use App\Models\User;
use App\Services\SignatureReferenceService;
use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * A form with a single Compare-mode signature field bound to one expected profile.
 */
function compareForm(string $route, int $expectedProfileId): Form
{
    $form = Form::create([
        'name' => 'Cmp '.$route,
        'route_name' => $route,
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    FormDescription::create([
        'form_id' => (int) $form->getKey(),
        'is_required' => true,
        'field_order' => 1,
        'field_key' => 'sig',
        'field_label' => 'Authorized Signature',
        'field_type' => 'signature',
        'field_options' => ['match_mode' => 'compare', 'expected_profiles' => [$expectedProfileId]],
    ]);

    return $form;
}

/** Create an organization row and return its id. */
function cmpOrg(): int
{
    $detailId = DB::table('organization_details')->insertGetId([
        'name' => 'Cmp Org '.Str::random(4),
        'detail_text' => 'Cmp Org',
        'initials' => 'CM',
    ]);

    return (int) DB::table('organizations')->insertGetId([
        'organization_type' => 1,
        'detail' => $detailId,
    ]);
}

/** Attach a user to an organization as an officer with the given role. */
function cmpOfficer(int $orgId, User $user, string $role = 'officer'): void
{
    DB::table('organization_officers')->insert([
        'role' => $role,
        'organization' => $orgId,
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);
}

/** An officer of a throwaway org, so the officer-gated form is reachable. */
function cmpSubmitter(): User
{
    $user = recordsUser(3);
    cmpOfficer(cmpOrg(), $user);

    return $user;
}

/** A profile carrying a stored signature file (on the faked disk). */
function cmpProfileWithSignature(string $first, string $last): Profile
{
    $path = 'signatures/expected/'.Str::random(8).'.png';
    Storage::disk(SignatureImage::disk())->put($path, 'expected-signature-bytes');

    return Profile::create([
        'first_name' => $first,
        'middle_name' => '',
        'last_name' => $last,
        'occupation' => '',
        'signature_path' => $path,
    ]);
}

// ---------------------------------------------------------------------------
// End-to-end Compare-mode submit
// ---------------------------------------------------------------------------

it('rejects a submission whose signature does not match the expected signer', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $expected = cmpProfileWithSignature('Expected', 'Signer');
    compareForm('cmp-mismatch', (int) $expected->profile_id);

    Http::fake(['*/signature-identify' => Http::response(['match' => false, 'best' => null], 200)]);

    $this->actingAs(cmpSubmitter())
        ->post(route('forms.render.submit', 'cmp-mismatch'), ['sig' => signatureDataUrl(1)])
        ->assertSessionHasErrors('sig');

    expect(DB::table('form_submissions')->count())->toBe(0);
});

it('accepts a matching signature and records the verification in the payload', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $expected = cmpProfileWithSignature('Match', 'Signer');
    compareForm('cmp-match', (int) $expected->profile_id);

    // Pre-sync so we know the reference_id the sidecar should return as the match
    // (resolve() re-syncs idempotently by profile, keeping the same id).
    $reference = app(SignatureReferenceService::class)->syncFromProfile($expected);

    Http::fake(['*/signature-identify' => Http::response([
        'match' => true,
        'best' => ['id' => (int) $reference->reference_id, 'score' => 0.94],
    ], 200)]);

    $this->actingAs(cmpSubmitter())
        ->post(route('forms.render.submit', 'cmp-match'), ['sig' => signatureDataUrl(2)])
        ->assertRedirect();

    $submission = DB::table('form_submissions')->orderByDesc('form_submission_id')->first();
    expect($submission)->not->toBeNull();

    $payload = json_decode((string) $submission->payload, true);
    expect($payload['_signature_verification']['sig']['status'])->toBe('verified')
        ->and($payload['_signature_verification']['sig']['matched'])->toBe('Match Signer');
});

it('rejects (fail-closed) when the OCR sidecar is unreachable', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $expected = cmpProfileWithSignature('Down', 'Signer');
    compareForm('cmp-down', (int) $expected->profile_id);

    Http::fake(['*/signature-identify' => Http::response('', 500)]);

    $this->actingAs(cmpSubmitter())
        ->post(route('forms.render.submit', 'cmp-down'), ['sig' => signatureDataUrl(3)])
        ->assertSessionHasErrors('sig');

    expect(DB::table('form_submissions')->count())->toBe(0);
});

it('rejects (fail-closed) when the expected signer has no signature on file', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    // Expected profile exists but has NO signature — nothing to compare against.
    $expected = Profile::create([
        'first_name' => 'No', 'middle_name' => '', 'last_name' => 'Signature',
        'occupation' => '', 'signature_path' => null,
    ]);
    compareForm('cmp-noref', (int) $expected->profile_id);

    // The sidecar must never even be consulted; fake it to prove the gate is earlier.
    Http::fake(['*/signature-identify' => Http::response(['match' => true, 'best' => ['id' => 1, 'score' => 1.0]], 200)]);

    $this->actingAs(cmpSubmitter())
        ->post(route('forms.render.submit', 'cmp-noref'), ['sig' => signatureDataUrl(4)])
        ->assertSessionHasErrors('sig');

    expect(DB::table('form_submissions')->count())->toBe(0);
    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Org-scoped position resolution
// ---------------------------------------------------------------------------

it('resolves an org position to that organization\'s current officer, scoped per org', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $options = ['match_mode' => 'compare', 'expected_positions' => ['President']];

    // Org A has a president (with a signature) plus an ordinary submitter.
    $orgA = cmpOrg();
    $presidentProfile = cmpProfileWithSignature('Percy', 'President');
    $presidentUser = User::query()->create([
        'user_email' => 'pres'.Str::random(6).'@example.com',
        'user_password' => 'password',
        'user_type' => 3,
        'profile' => (int) $presidentProfile->getKey(),
    ]);
    cmpOfficer($orgA, $presidentUser, 'president');

    $submitterA = recordsUser(3);
    cmpOfficer($orgA, $submitterA, 'officer');

    $references = app(ExpectedSignatories::class)->resolve($options, $submitterA);

    expect($references)->toHaveCount(1)
        ->and((int) $references->first()->profile_id)->toBe((int) $presidentProfile->profile_id);

    // Org B has a submitter but no president — the same field resolves to nothing.
    $orgB = cmpOrg();
    $submitterB = recordsUser(3);
    cmpOfficer($orgB, $submitterB, 'officer');

    expect(app(ExpectedSignatories::class)->resolve($options, $submitterB))->toHaveCount(0);
});

it('compares expected position signatures against the submitted organization when one is selected', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    $form = Form::create([
        'name' => 'Selected Org Compare',
        'route_name' => 'cmp-selected-org',
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    FormDescription::create([
        'form_id' => (int) $form->getKey(),
        'is_required' => true,
        'field_order' => 1,
        'field_key' => 'organization_id',
        'field_label' => 'Organization',
        'field_type' => 'org-select',
    ]);
    FormDescription::create([
        'form_id' => (int) $form->getKey(),
        'is_required' => true,
        'field_order' => 2,
        'field_key' => 'sig',
        'field_label' => 'President Signature',
        'field_type' => 'signature',
        'field_options' => ['match_mode' => 'compare', 'expected_positions' => ['President']],
    ]);

    $orgA = cmpOrg();
    $orgB = cmpOrg();

    $presidentA = cmpProfileWithSignature('Org A', 'President');
    $presidentAUser = User::query()->create([
        'user_email' => 'pres-a'.Str::random(6).'@example.com',
        'user_password' => 'password',
        'user_type' => 3,
        'profile' => (int) $presidentA->getKey(),
    ]);
    cmpOfficer($orgA, $presidentAUser, 'president');

    $presidentB = cmpProfileWithSignature('Org B', 'President');
    $presidentBUser = User::query()->create([
        'user_email' => 'pres-b'.Str::random(6).'@example.com',
        'user_password' => 'password',
        'user_type' => 3,
        'profile' => (int) $presidentB->getKey(),
    ]);
    cmpOfficer($orgB, $presidentBUser, 'president');

    $submitter = recordsUser(3);
    cmpOfficer($orgA, $submitter, 'officer');
    cmpOfficer($orgB, $submitter, 'officer');

    $reference = app(SignatureReferenceService::class)->syncFromProfile($presidentA);

    Http::fake(['*/signature-identify' => Http::response([
        'match' => true,
        'best' => ['id' => (int) $reference->reference_id, 'score' => 0.96],
    ], 200)]);

    $this->actingAs($submitter)
        ->post(route('forms.render.submit', 'cmp-selected-org'), [
            'organization_id' => $orgA,
            'sig' => signatureDataUrl(5),
        ])
        ->assertRedirect();

    $submission = DB::table('form_submissions')->orderByDesc('form_submission_id')->first();
    $payload = json_decode((string) $submission->payload, true);

    expect($payload['_signature_verification']['sig']['matched'])->toBe('Org A President');
});
