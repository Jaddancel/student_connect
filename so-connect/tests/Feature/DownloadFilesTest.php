<?php

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->publicDiskRoot = sys_get_temp_dir().'/download-files-'.bin2hex(random_bytes(8));
    config(['filesystems.disks.public.root' => $this->publicDiskRoot]);
    Storage::forgetDisk('public');
});

afterEach(function () {
    File::deleteDirectory($this->publicDiskRoot);
    Storage::forgetDisk('public');
});

it('filters the shared file list and identifies external links', function () {
    $author = recordsUser(3, [
        'first_name' => 'Ari',
        'middle_name' => 'Q',
        'last_name' => 'Staff',
    ]);
    Document::query()->create([
        'description_text' => 'October minutes',
        'author' => $author->getKey(),
        'link' => 'documents/october-minutes.pdf',
    ]);
    Document::query()->create([
        'description_text' => 'Policy reference',
        'link' => 'https://example.test/policy.pdf',
    ]);

    $this->actingAs(recordsUser(3))
        ->get(route('download-files', ['q' => 'minutes']))
        ->assertOk()
        ->assertViewHas('queryText', 'minutes')
        ->assertViewHas('documents', function ($documents) {
            return $documents->count() === 1
                && $documents->first()->description_text === 'October minutes'
                && $documents->first()->author_name === 'Ari Q Staff'
                && $documents->first()->is_external === false;
        });
});

it('downloads stored files for signed-in users', function () {
    Storage::disk('public')->put('documents/meeting-minutes.pdf', '%PDF-1.4 test');
    $document = Document::query()->create([
        'description_text' => 'Meeting minutes',
        'link' => 'documents/meeting-minutes.pdf',
    ]);

    $this->actingAs(recordsUser(3))
        ->get(route('download-files.download', $document->getKey()))
        ->assertOk()
        ->assertDownload('meeting-minutes.pdf');
});

it('rejects external links and reports missing stored files', function () {
    $user = recordsUser(3);
    $external = Document::query()->create([
        'description_text' => 'External resource',
        'link' => 'https://example.test/resource.pdf',
    ]);
    $missing = Document::query()->create([
        'description_text' => 'Missing file',
        'link' => 'documents/missing.pdf',
    ]);

    $this->actingAs($user)
        ->get(route('download-files.download', $external->getKey()))
        ->assertNotFound();

    $this->from(route('download-files'))
        ->actingAs($user)
        ->get(route('download-files.download', $missing->getKey()))
        ->assertRedirect(route('download-files'))
        ->assertSessionHas('status', 'The selected file is no longer available in storage.');
});
