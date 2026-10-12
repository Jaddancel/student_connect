<?php

use App\Forms\FieldType;
use App\Forms\SubmissionPresenter;
use App\Support\ImageMetadata;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

uses(TestCase::class);

it('reads embedded HEIC metadata without presenting the source path as metadata', function () {
    Process::fake([
        '*' => Process::result(output: json_encode([[
            'SourceFile' => '/tmp/photo.heic',
            'IFD0:Make' => 'Apple',
            'ExifIFD:DateTimeOriginal' => '2026:10:12 12:00:00',
        ]])),
    ]);

    expect(ImageMetadata::read('/tmp/photo.heic'))->toBe([
        'IFD0:Make' => 'Apple',
        'ExifIFD:DateTimeOriginal' => '2026:10:12 12:00:00',
    ]);
    Process::assertRan(fn ($process) => $process->command === [
        'exiftool', '-json', '-G1', '-s', '-EXIF:all', '-XMP:all', '-IPTC:all', '/tmp/photo.heic',
    ]);
});

it('reports genuinely absent HEIC metadata as missing', function () {
    Process::fake(['*' => Process::result(output: '[{"SourceFile":"/tmp/photo.heic"}]')]);

    expect(ImageMetadata::read('/tmp/photo.heic'))->toBe([]);
});

it('reads embedded metadata for every supported image extension', function (string $extension) {
    Storage::fake('public');
    $path = 'photo.'.$extension;
    Storage::disk('public')->put($path, 'fixture');
    Process::fake(['*' => Process::result(output: '[{"IFD0:Make":"Apple"}]')]);

    $items = SubmissionPresenter::attachments(['photo' => $path], 'photo', FieldType::IMAGE, 'public');

    expect($items[0]['metadata'])->toBe(['EXIF.IFD0:Make' => 'Apple'])
        ->and($items[0]['metadata_error'])->toBeNull();
})->with(['jpe', 'jpeg', 'jpg', 'png', 'gif', 'webp', 'heic', 'heif', 'bmp', 'svg']);

it('distinguishes reader failure from missing HEIC metadata', function () {
    Storage::fake('public');
    Storage::disk('public')->put('photo.heic', 'fixture');
    Process::fake(['*' => Process::result(errorOutput: 'exiftool: not found', exitCode: 127)]);
    Log::shouldReceive('warning')->once();

    $items = SubmissionPresenter::attachments(['photo' => 'photo.heic'], 'photo', FieldType::IMAGE, 'public');

    expect($items[0]['metadata_error'])->toBe('EXIF data unavailable');
});
