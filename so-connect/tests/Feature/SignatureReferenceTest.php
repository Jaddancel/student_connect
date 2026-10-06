<?php

use App\Models\Profile;
use App\Models\SignatureReference;
use App\Services\SignatureReferenceService;
use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function putSignature(string $path, string $contents = 'img'): void
{
    Storage::disk(SignatureImage::disk())->put($path, $contents);
}

it('returns only references whose image exists, with a name map', function () {
    Storage::fake(SignatureImage::disk());
    putSignature('signatures/a.png');

    $present = SignatureReference::create(['name' => 'Alice Ref', 'signature_path' => 'signatures/a.png', 'source' => 'profile']);
    SignatureReference::create(['name' => 'Ghost', 'signature_path' => 'signatures/missing.png', 'source' => 'profile']);

    [$candidates, $names] = app(SignatureReferenceService::class)->candidates();

    expect($candidates)->toHaveCount(1);
    expect($candidates[0]['id'])->toBe((int) $present->reference_id);
    expect($names[(int) $present->reference_id])->toBe('Alice Ref');
});

it('builds candidates from a supplied reference set only', function () {
    Storage::fake(SignatureImage::disk());
    putSignature('signatures/a.png');
    putSignature('signatures/b.png');

    $wanted = SignatureReference::create(['name' => 'Wanted', 'signature_path' => 'signatures/a.png', 'source' => 'profile']);
    SignatureReference::create(['name' => 'Other', 'signature_path' => 'signatures/b.png', 'source' => 'profile']);

    [$candidates, $names] = app(SignatureReferenceService::class)->candidatesFrom(collect([$wanted]));

    expect($candidates)->toHaveCount(1);
    expect($candidates[0]['id'])->toBe((int) $wanted->reference_id);
    expect($names[(int) $wanted->reference_id])->toBe('Wanted');
});

it('mirrors a profile signature into the registry and is idempotent', function () {
    Storage::fake(SignatureImage::disk());
    putSignature('signatures/p.png');
    $profile = Profile::create(['first_name' => 'Bob', 'last_name' => 'Lee', 'middle_name' => 'M', 'occupation' => 'x', 'signature_path' => 'signatures/p.png']);

    $service = app(SignatureReferenceService::class);
    $service->syncFromProfile($profile);
    $service->syncFromProfile($profile->fresh());

    $refs = SignatureReference::where('profile_id', $profile->profile_id)->get();
    expect($refs)->toHaveCount(1);
    expect($refs->first()->source)->toBe('profile');
    expect($refs->first()->name)->toBe('Bob Lee');
});

it('drops the reference when a profile clears its signature', function () {
    Storage::fake(SignatureImage::disk());
    putSignature('signatures/p.png');
    $profile = Profile::create(['first_name' => 'Cara', 'last_name' => 'Diaz', 'middle_name' => 'M', 'occupation' => 'x', 'signature_path' => 'signatures/p.png']);
    $service = app(SignatureReferenceService::class);
    $service->syncFromProfile($profile);

    $profile->update(['signature_path' => null]);
    $service->syncFromProfile($profile->fresh());

    expect(SignatureReference::where('profile_id', $profile->profile_id)->exists())->toBeFalse();
});

it('backfills references from all profile signatures', function () {
    Storage::fake(SignatureImage::disk());
    putSignature('signatures/1.png');
    putSignature('signatures/2.png');
    Profile::create(['first_name' => 'A', 'last_name' => 'One', 'middle_name' => 'M', 'occupation' => 'x', 'signature_path' => 'signatures/1.png']);
    Profile::create(['first_name' => 'B', 'last_name' => 'Two', 'middle_name' => 'M', 'occupation' => 'x', 'signature_path' => 'signatures/2.png']);
    Profile::create(['first_name' => 'C', 'last_name' => 'None', 'middle_name' => 'M', 'occupation' => 'x', 'signature_path' => null]);

    expect(app(SignatureReferenceService::class)->backfill())->toBe(2);
    expect(SignatureReference::where('source', 'profile')->count())->toBe(2);
});

it('reports a recognized signature via the verify endpoint', function () {
    Storage::fake(SignatureImage::disk());
    putSignature('signatures/a.png');
    $ref = SignatureReference::create(['name' => 'Alice Ref', 'signature_path' => 'signatures/a.png', 'source' => 'profile']);

    Http::fake([
        '*/signature-identify' => Http::response(['match' => true, 'best' => ['id' => $ref->reference_id, 'score' => 0.92]], 200),
    ]);

    $this->actingAs(recordsUser(3))
        ->postJson(route('signature.verify'), ['signature' => 'data:image/png;base64,'.base64_encode('probe')])
        ->assertOk()
        ->assertJson(['status' => 'recognized', 'matched_user' => 'Alice Ref']);
});

it('reports no_signatures when the registry is empty', function () {
    Storage::fake(SignatureImage::disk());

    $this->actingAs(recordsUser(3))
        ->postJson(route('signature.verify'), ['signature' => 'data:image/png;base64,'.base64_encode('probe')])
        ->assertOk()
        ->assertJson(['status' => 'no_signatures']);
});

it('names an unrecognized signature, creating one flagged profile and a profile-sourced reference', function () {
    Storage::fake(SignatureImage::disk());

    $this->actingAs(recordsUser(3))
        ->postJson(route('signature.enroll'), [
            'signature' => signatureDataUrl(),
            'name' => 'Cruz, Jane Marie',
        ])
        ->assertOk()
        ->assertJson(['status' => 'saved']);

    $profile = Profile::where('last_name', 'Cruz')->first();
    expect($profile)->not->toBeNull();
    expect($profile->first_name)->toBe('Jane Marie');
    expect($profile->origin)->toBe(Profile::ORIGIN_SIGNATURE_ONLY);
    expect(Storage::disk(SignatureImage::disk())->exists($profile->signature_path))->toBeTrue();

    $ref = SignatureReference::where('profile_id', $profile->profile_id)->first();
    expect($ref)->not->toBeNull();
    expect($ref->source)->toBe('profile');
});

it('does not create a second profile when the same name is submitted twice', function () {
    Storage::fake(SignatureImage::disk());

    foreach (['first', 'second'] as $_) {
        $this->actingAs(recordsUser(3))
            ->postJson(route('signature.enroll'), [
                'signature' => signatureDataUrl(),
                'name' => 'Jane External',
            ])
            ->assertOk();
    }

    expect(Profile::where('last_name', 'External')->count())->toBe(1);
});

it('refuses to name an image with no signature in it', function () {
    Storage::fake(SignatureImage::disk());

    $blank = imagecreatetruecolor(400, 200);
    imagefill($blank, 0, 0, imagecolorallocate($blank, 252, 252, 250));
    ob_start();
    imagepng($blank);
    $bytes = (string) ob_get_clean();

    $this->actingAs(recordsUser(3))
        ->postJson(route('signature.enroll'), [
            'signature' => 'data:image/png;base64,'.base64_encode($bytes),
            'name' => 'Blank Page',
        ])
        ->assertStatus(422);

    expect(Profile::where('last_name', 'Page')->exists())->toBeFalse();
    expect(Storage::disk(SignatureImage::disk())->allFiles())->toBe([]);
});
