<?php

use App\Models\IdTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('lets an admin create a waiver template with validated zones', function () {
    Storage::fake((string) config('documents.disk', 'public'));

    $this->actingAs(recordsUser(2))
        ->post(route('admin.waiver-templates.store'), [
            'name' => 'Event Waiver',
            'image' => UploadedFile::fake()->image('w.png', 800, 600),
            'image_width' => 800,
            'image_height' => 600,
            'zones' => [
                ['name' => 'participant', 'type' => 'text', 'x' => 10, 'y' => 10, 'w' => 100, 'h' => 30],
                ['name' => 'seal', 'type' => 'stamp', 'x' => 200, 'y' => 200, 'w' => 80, 'h' => 80],
            ],
        ])
        ->assertRedirect();

    $template = IdTemplate::where('kind', 'waiver')->where('name', 'Event Waiver')->first();
    expect($template)->not->toBeNull();
    expect($template->zones)->toHaveCount(2);
});

it('rejects a waiver template with an invalid zone type', function () {
    Storage::fake((string) config('documents.disk', 'public'));

    $this->actingAs(recordsUser(2))
        ->from(route('admin.waiver-templates.index'))
        ->post(route('admin.waiver-templates.store'), [
            'name' => 'Bad',
            'image' => UploadedFile::fake()->image('w.png'),
            'image_width' => 800,
            'image_height' => 600,
            'zones' => [['name' => 'x', 'type' => 'hologram', 'x' => 0, 'y' => 0, 'w' => 1, 'h' => 1]],
        ])
        ->assertSessionHasErrors('zones');

    expect(IdTemplate::where('name', 'Bad')->exists())->toBeFalse();
});

it('forbids non-admins from waiver templates', function () {
    $this->actingAs(recordsUser(3))
        ->get(route('admin.waiver-templates.index'))
        ->assertForbidden();
});

it('lets an admin delete a waiver template', function () {
    $template = IdTemplate::create([
        'name' => 'X', 'kind' => 'waiver', 'image_path' => 'x.png',
        'image_width' => 1, 'image_height' => 1, 'zones' => [], 'is_active' => true,
    ]);

    $this->actingAs(recordsUser(2))
        ->delete(route('admin.waiver-templates.destroy', $template->id_template_id))
        ->assertRedirect();

    expect(IdTemplate::find($template->id_template_id))->toBeNull();
});
