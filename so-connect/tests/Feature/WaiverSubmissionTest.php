<?php

use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\IdTemplate;
use App\Services\WaiverSubmissionService;
use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function waiverField(string $route = 'wf'): FormDescription
{
    $form = Form::create(['name' => 'Waiver Form', 'route_name' => $route, 'is_active' => true]);

    return FormDescription::create([
        'form_id' => $form->id, 'field_key' => 'waiver', 'field_label' => 'Waiver',
        'field_type' => 'waiver-scan', 'field_order' => 1,
    ]);
}

it('stores a scanned waiver and records the server re-validation', function () {
    Storage::fake(SignatureImage::disk());
    IdTemplate::create([
        'name' => 'W', 'kind' => 'waiver', 'image_path' => 'x', 'image_width' => 800, 'image_height' => 600,
        'zones' => [['name' => 'p', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10]], 'is_active' => true,
    ]);
    Http::fake(['*/waiver-scan' => Http::response(['ok' => true, 'fields' => ['p' => 'x'], 'stamp' => true, 'signature' => true], 200)]);

    $result = app(WaiverSubmissionService::class)->process(
        'data:image/png;base64,'.base64_encode('img'),
        waiverField(),
    );

    expect($result['path'])->not->toBeNull();
    expect(Storage::disk(SignatureImage::disk())->exists($result['path']))->toBeTrue();
    expect($result['validation']['stamp_ok'])->toBeTrue();
});

it('leaves an already-stored path untouched', function () {
    $result = app(WaiverSubmissionService::class)->process('waivers/2026/07/x.png', waiverField('wf2'));

    expect($result['path'])->toBe('waivers/2026/07/x.png');
    expect($result['validation'])->toBeNull();
});

it('shows the waiver review page to a type-2 admin and hides it from officers', function () {
    $field = waiverField('wf3');
    FormSubmission::create([
        'form_id' => $field->form_id, 'organization_id' => null, 'submitted_by' => null,
        'payload' => ['waiver' => 'waivers/x.png', '_waiver_validation' => ['waiver' => ['valid' => false]]],
        'submitted_at' => now(),
    ]);

    $this->actingAs(recordsUser(2))
        ->get(route('admin.waiver-review.index'))
        ->assertOk()
        ->assertSee('Waiver Form')
        ->assertSee('Needs review');

    $this->actingAs(recordsUser(3))
        ->get(route('admin.waiver-review.index'))
        ->assertForbidden();
});
