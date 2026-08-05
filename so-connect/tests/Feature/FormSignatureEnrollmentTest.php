<?php

use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\SignatureReference;
use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function sigForm(string $route, array $fields): Form
{
    $form = Form::create([
        'name' => 'Sig '.$route,
        'route_name' => $route,
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    foreach ($fields as $order => $field) {
        FormDescription::create(array_merge([
            'form_id' => $form->id,
            'is_required' => false,
            'field_order' => $order + 1,
        ], $field));
    }

    return $form;
}

function drawnSignature(int $seed = 0): string
{
    return signatureDataUrl($seed);
}

/** An officer of some organization — builder forms are gated to officers. */
function sigUser(array $profileAttributes = []): \App\Models\User
{
    $user = recordsUser(3, $profileAttributes);

    $detailId = \Illuminate\Support\Facades\DB::table('organization_details')->insertGetId([
        'name' => 'Sig Org '.\Illuminate\Support\Str::random(4),
        'detail_text' => 'Sig Org',
        'initials' => 'SG',
    ]);
    $orgId = \Illuminate\Support\Facades\DB::table('organizations')->insertGetId([
        'organization_type' => 1,
        'detail' => $detailId,
    ]);
    \Illuminate\Support\Facades\DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => $orgId,
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return $user;
}

it('registers a signature captured on a form under the name typed on that form', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    sigForm('waiver-sig', [
        ['field_key' => 'printed_name', 'field_label' => 'Name of Participant', 'field_type' => 'text'],
        ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature'],
    ]);

    $this->actingAs(sigUser())
        ->post(route('forms.render.submit', 'waiver-sig'), [
            'printed_name' => 'Maria Santos',
            'sig' => drawnSignature(),
        ])
        ->assertRedirect();

    $reference = SignatureReference::query()->where('name', 'Maria Santos')->first();

    expect($reference)->not->toBeNull()
        ->and($reference->source)->toBe('enrolled')
        ->and(Storage::disk(SignatureImage::disk())->exists($reference->signature_path))->toBeTrue();
});

it('files each signature under the name nearest to it', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    sigForm('two-sigs', [
        ['field_key' => 'participant', 'field_label' => 'Participant Name', 'field_type' => 'text'],
        ['field_key' => 'participant_sig', 'field_label' => 'Signature', 'field_type' => 'signature'],
        ['field_key' => 'guardian', 'field_label' => 'Guardian Name', 'field_type' => 'text'],
        ['field_key' => 'guardian_sig', 'field_label' => 'Signature', 'field_type' => 'signature'],
    ]);

    $this->actingAs(sigUser())
        ->post(route('forms.render.submit', 'two-sigs'), [
            'participant' => 'Ana Cruz',
            'participant_sig' => drawnSignature(1),
            'guardian' => 'Ben Cruz',
            'guardian_sig' => drawnSignature(2),
        ])
        ->assertRedirect();

    expect(SignatureReference::query()->pluck('name')->sort()->values()->all())
        ->toBe(['Ana Cruz', 'Ben Cruz']);
});

it('joins split first/last name fields into one owner name', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    sigForm('split-name', [
        ['field_key' => 'fn', 'field_label' => 'First Name', 'field_type' => 'text', 'universal_key' => 'first_name'],
        ['field_key' => 'ln', 'field_label' => 'Last Name', 'field_type' => 'text', 'universal_key' => 'last_name'],
        ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature'],
    ]);

    $this->actingAs(sigUser())
        ->post(route('forms.render.submit', 'split-name'), [
            'fn' => 'Jose',
            'ln' => 'Rizal',
            'sig' => drawnSignature(),
        ])
        ->assertRedirect();

    expect(SignatureReference::query()->where('name', 'Jose Rizal')->exists())->toBeTrue();
});

it('adopts the submitter signature field as their profile signature when they have none', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    sigForm('own-sig', [
        ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature', 'universal_key' => 'signature'],
    ]);

    $user = sigUser(['first_name' => 'Pedro', 'last_name' => 'Reyes']);
    expect($user->profile()->first()->signature_path)->toBeNull();

    $this->actingAs($user)
        ->post(route('forms.render.submit', 'own-sig'), ['sig' => drawnSignature()])
        ->assertRedirect();

    $profile = $user->profile()->first();
    expect($profile->signature_path)->not->toBeNull();

    $reference = SignatureReference::query()->where('profile_id', $profile->getKey())->first();
    expect($reference)->not->toBeNull()
        ->and($reference->source)->toBe('profile')
        ->and($reference->name)->toBe('Pedro Reyes');
});

it('does not overwrite an existing profile signature', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    sigForm('own-sig-2', [
        ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature', 'universal_key' => 'signature'],
    ]);

    $user = sigUser(['first_name' => 'Rosa', 'last_name' => 'Lim']);
    $user->profile()->first()->update(['signature_path' => 'signatures/profiles/existing.png']);

    $this->actingAs($user)
        ->post(route('forms.render.submit', 'own-sig-2'), ['sig' => drawnSignature()])
        ->assertRedirect();

    expect($user->profile()->first()->signature_path)->toBe('signatures/profiles/existing.png');
});

it('refreshes the existing reference instead of stacking one per submission', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    sigForm('repeat-sig', [
        ['field_key' => 'printed_name', 'field_label' => 'Name of Participant', 'field_type' => 'text'],
        ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature'],
    ]);

    $user = sigUser();
    foreach ([1, 2] as $seed) {
        $this->actingAs($user)->post(route('forms.render.submit', 'repeat-sig'), [
            'printed_name' => 'Maria Santos',
            'sig' => drawnSignature($seed),
        ])->assertRedirect();
    }

    expect(SignatureReference::query()->where('name', 'Maria Santos')->count())->toBe(1);
});

it('stores the extracted ink from a submitted photo, not the photo', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    sigForm('photo-sig', [
        ['field_key' => 'printed_name', 'field_label' => 'Name of Participant', 'field_type' => 'text'],
        ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature'],
    ]);

    $photo = paperPhoto();

    $this->actingAs(sigUser())
        ->post(route('forms.render.submit', 'photo-sig'), [
            'printed_name' => 'Maria Santos',
            'sig' => 'data:image/png;base64,'.base64_encode($photo),
        ])
        ->assertRedirect();

    $stored = Storage::disk('public')->get(
        SignatureReference::query()->where('name', 'Maria Santos')->value('signature_path'),
    );

    expect($stored)->not->toBe($photo);

    // The 900x700 photo is reduced to a crop of the stroke alone.
    $image = imagecreatefromstring($stored);
    expect(imagesx($image))->toBeLessThan(500)
        ->and(imagesy($image))->toBeLessThan(200);
});

it('rejects a submitted signature photo with no readable ink', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    sigForm('bad-sig', [
        ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature'],
    ]);

    $this->actingAs(sigUser())
        ->post(route('forms.render.submit', 'bad-sig'), [
            'sig' => 'data:image/png;base64,'.base64_encode(flatImage(400, 300, 250, 250, 248)),
        ])
        ->assertSessionHasErrors('sig');

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('does not enroll when a saved signature is re-used unchanged', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);

    sigForm('reuse-sig', [
        ['field_key' => 'printed_name', 'field_label' => 'Name of Participant', 'field_type' => 'text'],
        ['field_key' => 'sig', 'field_label' => 'Signature', 'field_type' => 'signature'],
    ]);

    $user = sigUser();
    $user->profile()->first()->update(['signature_path' => 'signatures/profiles/saved.png']);

    $this->actingAs($user)->post(route('forms.render.submit', 'reuse-sig'), [
        'printed_name' => 'Maria Santos',
        'sig' => 'signatures/profiles/saved.png',
    ])->assertRedirect();

    expect(SignatureReference::query()->where('source', 'enrolled')->count())->toBe(0);
});
