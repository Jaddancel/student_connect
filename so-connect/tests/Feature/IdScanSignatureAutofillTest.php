<?php

use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\IdTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Covers the path a signature takes from an ID template's signature zone into
 * the form's signature box: the scan endpoint hands the crop back keyed by the
 * zone's `field`, and the rendered signature field carries the marker the
 * ID-scan wizard needs to find it (see resources/js/components/id-scan-wizard.js).
 */
function idSignatureTemplate(array $zoneOverrides = []): IdTemplate
{
    return IdTemplate::query()->create([
        'name' => 'Signature ID',
        'orientation' => 'vertical',
        'image_path' => 'id-templates/reference.jpg',
        'image_width' => 1000,
        'image_height' => 600,
        'is_active' => true,
        'is_default' => true,
        'back_image_path' => 'id-templates/reference-back.jpg',
        'back_image_width' => 1000,
        'back_image_height' => 600,
        'back_zones' => [],
        'zones' => [array_merge([
            'name' => 'zone_3', 'label' => 'Signature',
            'x1' => 100, 'y1' => 400, 'x2' => 500, 'y2' => 500,
            'regex' => null, 'field' => 'signature', 'type' => 'signature',
        ], $zoneOverrides)],
    ]);
}

it('hands a signature zone crop back under the zone destination field', function () {
    $crop = 'data:image/png;base64,iVBORw0KGgo=';
    Http::fake(['*/scan' => Http::response([
        'fields' => [], 'raw' => [], 'images' => ['zone_3' => $crop],
    ], 200)]);

    $template = idSignatureTemplate();

    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
        'template_id' => $template->getKey(),
    ])
        ->assertOk()
        ->assertJsonPath('images.signature', $crop);
});

it('ignores a zone crop that is not an image', function () {
    Http::fake(['*/scan' => Http::response([
        'fields' => [], 'raw' => [], 'images' => ['zone_3' => 'not-an-image'],
    ], 200)]);

    $this->post(route('id-scan.scan'), [
        'photo' => UploadedFile::fake()->image('front.jpg', 1000, 600),
        'template_id' => idSignatureTemplate()->getKey(),
    ])
        ->assertOk()
        ->assertJsonPath('images', []);
});

it('marks a rendered signature field so the ID-scan wizard can fill it unbound', function () {
    $form = Form::create([
        'name' => 'Officer Sign Up',
        'route_name' => 'id-sig-signup',
        'system_function' => 'sign_up', // public: no auth needed to render
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>x</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    foreach ([
        ['field_key' => 'student_id', 'field_label' => 'Student ID number', 'field_type' => 'id-scan'],
        // Deliberately unbound (universal_key null): the wizard must still find it.
        ['field_key' => 'signature', 'field_label' => 'Signature', 'field_type' => 'signature'],
    ] as $order => $field) {
        FormDescription::create(array_merge([
            'form_id' => $form->id,
            'is_required' => false,
            'field_order' => $order + 1,
        ], $field));
    }

    $html = $this->get(route('forms.render', 'id-sig-signup'))->assertOk()->getContent();

    expect(substr_count($html, 'data-signature-field'))->toBe(1)
        ->and($html)->not->toContain('data-universal-key="signature"');
});
