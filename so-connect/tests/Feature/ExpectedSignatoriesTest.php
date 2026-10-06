<?php

use App\Forms\ExpectedSignatories;
use App\Models\Profile;
use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('resolves only the expected profiles, and only those with a signature on file', function () {
    Storage::fake(SignatureImage::disk());
    Storage::disk(SignatureImage::disk())->put('signatures/a.png', 'img');

    $withSig = Profile::create([
        'first_name' => 'Ann', 'middle_name' => '', 'last_name' => 'Signer',
        'occupation' => '', 'signature_path' => 'signatures/a.png',
    ]);
    $noSig = Profile::create([
        'first_name' => 'Ben', 'middle_name' => '', 'last_name' => 'Blank',
        'occupation' => '', 'signature_path' => null,
    ]);
    $unexpected = Profile::create([
        'first_name' => 'Cal', 'middle_name' => '', 'last_name' => 'Other',
        'occupation' => '', 'signature_path' => 'signatures/a.png',
    ]);

    $options = [
        'match_mode' => 'compare',
        'expected_profiles' => [$withSig->profile_id, $noSig->profile_id],
    ];

    $references = app(ExpectedSignatories::class)->resolve($options, null);

    expect($references)->toHaveCount(1);
    expect((int) $references->first()->profile_id)->toBe((int) $withSig->profile_id);
});

it('returns an empty set when no expected signer is configured', function () {
    $references = app(ExpectedSignatories::class)->resolve(['match_mode' => 'compare'], null);

    expect($references)->toHaveCount(0);
});
