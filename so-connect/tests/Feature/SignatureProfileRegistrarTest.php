<?php

use App\Models\Profile;
use App\Models\SignatureReference;
use App\Services\SignatureProfileRegistrar;
use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates a flagged profile and a profile-sourced reference for a new name', function () {
    Storage::fake(SignatureImage::disk());
    Storage::disk(SignatureImage::disk())->put('signatures/x.png', 'img');

    $profile = app(SignatureProfileRegistrar::class)->register('Cruz, Jane Marie', 'signatures/x.png');

    expect($profile)->not->toBeNull();
    expect($profile->first_name)->toBe('Jane Marie');
    expect($profile->last_name)->toBe('Cruz');
    expect($profile->origin)->toBe(Profile::ORIGIN_SIGNATURE_ONLY);

    $ref = SignatureReference::where('profile_id', $profile->profile_id)->first();
    expect($ref)->not->toBeNull();
    expect($ref->source)->toBe(SignatureReference::SOURCE_PROFILE);
});

it('dedupes by name instead of creating a second profile', function () {
    Storage::fake(SignatureImage::disk());
    Storage::disk(SignatureImage::disk())->put('signatures/a.png', 'img');
    Storage::disk(SignatureImage::disk())->put('signatures/b.png', 'img2');

    $registrar = app(SignatureProfileRegistrar::class);
    $first = $registrar->register('Jane External', 'signatures/a.png');
    $second = $registrar->register('jane external', 'signatures/b.png');

    expect($second->profile_id)->toBe($first->profile_id);
    expect(Profile::where('last_name', 'External')->count())->toBe(1);
});

it('adopts a signature onto an existing profile that has none, without overwriting one that does', function () {
    Storage::fake(SignatureImage::disk());
    Storage::disk(SignatureImage::disk())->put('signatures/new.png', 'img');

    $registered = Profile::create([
        'first_name' => 'Real', 'middle_name' => '', 'last_name' => 'Person',
        'occupation' => 'Adviser', 'signature_path' => null,
        'origin' => Profile::ORIGIN_REGISTERED,
    ]);

    $result = app(SignatureProfileRegistrar::class)->register('Real Person', 'signatures/new.png');

    expect($result->profile_id)->toBe($registered->profile_id);
    expect($result->fresh()->signature_path)->toBe('signatures/new.png');
    // The profile stays a real registration — it is not downgraded to a placeholder.
    expect($result->fresh()->origin)->toBe(Profile::ORIGIN_REGISTERED);
});

it('returns null for a nameless value', function () {
    Storage::fake(SignatureImage::disk());
    Storage::disk(SignatureImage::disk())->put('signatures/x.png', 'img');

    expect(app(SignatureProfileRegistrar::class)->register('   ', 'signatures/x.png'))->toBeNull();
    expect(Profile::count())->toBe(0);
});
