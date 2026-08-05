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

    $present = SignatureReference::create(['name' => 'Alice Ref', 'signature_path' => 'signatures/a.png', 'source' => 'enrolled']);
    SignatureReference::create(['name' => 'Ghost', 'signature_path' => 'signatures/missing.png', 'source' => 'enrolled']);

    [$candidates, $names] = app(SignatureReferenceService::class)->candidates();

    expect($candidates)->toHaveCount(1);
    expect($candidates[0]['id'])->toBe((int) $present->reference_id);
    expect($names[(int) $present->reference_id])->toBe('Alice Ref');
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
    $ref = SignatureReference::create(['name' => 'Alice Ref', 'signature_path' => 'signatures/a.png', 'source' => 'enrolled']);

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

it('auto-enrolls an unrecognized signature under a typed name', function () {
    Storage::fake(SignatureImage::disk());

    $this->actingAs(recordsUser(3))
        ->postJson(route('signature.enroll'), [
            'signature' => signatureDataUrl(),
            'name' => 'Jane External',
        ])
        ->assertOk()
        ->assertJson(['status' => 'enrolled']);

    $ref = SignatureReference::where('name', 'Jane External')->first();
    expect($ref)->not->toBeNull();
    expect($ref->source)->toBe('enrolled');
    expect(Storage::disk(SignatureImage::disk())->exists($ref->signature_path))->toBeTrue();
});

it('refuses to enroll an image with no signature in it', function () {
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

    expect(SignatureReference::where('name', 'Blank Page')->exists())->toBeFalse();
    expect(Storage::disk(SignatureImage::disk())->allFiles())->toBe([]);
});

it('shows the reference registry to a super admin and hides it from others', function () {
    SignatureReference::create(['name' => 'On File', 'signature_path' => 'signatures/x.png', 'source' => 'enrolled']);

    $this->actingAs(recordsUser(1))
        ->get(route('superadmin.signature-references.index'))
        ->assertOk()
        ->assertSee('On File');

    $this->actingAs(recordsUser(2))
        ->get(route('superadmin.signature-references.index'))
        ->assertForbidden();
});

it('lets a super admin delete an enrolled reference and its image', function () {
    Storage::fake(SignatureImage::disk());
    Storage::disk(SignatureImage::disk())->put('signatures/enrolled/x.png', 'img');
    $ref = SignatureReference::create(['name' => 'Bad', 'signature_path' => 'signatures/enrolled/x.png', 'source' => 'enrolled']);

    $this->actingAs(recordsUser(1))
        ->delete(route('superadmin.signature-references.destroy', $ref->reference_id))
        ->assertRedirect();

    expect(SignatureReference::find($ref->reference_id))->toBeNull();
    expect(Storage::disk(SignatureImage::disk())->exists('signatures/enrolled/x.png'))->toBeFalse();
});
