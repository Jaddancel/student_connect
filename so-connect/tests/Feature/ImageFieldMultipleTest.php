<?php

use App\Forms\DocxTemplateData;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Template;
use App\Models\User;
use App\Services\DocxTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function imageMultipleOfficer(): User
{
    $user = recordsUser(3);

    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => (int) recordsOrganization('Image Multiple Org', 'IMO')->getKey(),
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return $user;
}

function imageMultipleForm(array $options = ['multiple' => true, 'max_files' => 2], bool $required = false): Form
{
    $route = 'image-multiple-'.Str::lower(Str::random(8));
    $form = Form::query()->create([
        'name' => 'Image Multiple',
        'route_name' => $route,
        'is_active' => true,
        'is_published' => true,
        'layout' => ['rows' => []],
        'pdf_template' => ['html' => '<p>Photos</p>', 'page' => ['size' => 'a4', 'orientation' => 'portrait']],
    ]);

    FormDescription::query()->create([
        'form_id' => $form->id,
        'field_key' => 'photos',
        'field_label' => 'Photos',
        'field_type' => 'image',
        'is_required' => $required,
        'field_order' => 1,
        'field_options' => $options,
    ]);

    return $form;
}

function imageDocxTemplate(bool $table): Template
{
    $form = Form::query()->create([
        'name' => 'Image Docx',
        'route_name' => 'image-docx-'.Str::lower(Str::random(8)),
    ]);
    $relativePath = 'form-templates/'.$form->getKey().'/images.docx';
    Storage::disk('public')->put($relativePath, '');
    $path = Storage::disk('public')->path($relativePath);

    $phpWord = new PhpOffice\PhpWord\PhpWord;
    $section = $phpWord->addSection();
    if ($table) {
        $tableNode = $section->addTable(['borderSize' => 6]);
        $tableNode->addRow();
        $tableNode->addCell(3000)->addText('Photo');
        $tableNode->addRow();
        $tableNode->addCell(3000)->addText('{{photos#}}');
    } else {
        $section->addText('Photos: {{photos}}');
    }
    PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($path);

    return Template::query()->create([
        'form_id' => $form->getKey(),
        'template_name' => 'Image Template',
        'docx_path' => $relativePath,
        'version' => 1,
        'is_active' => true,
    ]);
}

function storedImagePath(string $name): string
{
    $relative = UploadedFile::fake()->image($name, 80, 60)->store('docx-images', 'public');

    return Storage::disk('public')->path($relative);
}

function docxImageXmlCount(string $path): int
{
    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open .docx: '.$path);
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    return substr_count($xml, '<v:imagedata') + substr_count($xml, '<a:blip');
}

it('validates max count and mimes for image fields in multiple mode', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = imageMultipleForm(['multiple' => true, 'max_files' => 2], true);
    $officer = imageMultipleOfficer();

    $this->actingAs($officer)
        ->post(route('forms.render.submit', $form->route_name), [
            'photos' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
            ],
        ])
        ->assertSessionHasErrors('photos');

    $this->actingAs($officer)
        ->post(route('forms.render.submit', $form->route_name), [
            'photos' => [UploadedFile::fake()->create('notes.txt', 4, 'text/plain')],
        ])
        ->assertSessionHasErrors('photos.0');
});

it('stores multiple image field uploads as an array of paths', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = imageMultipleForm(['multiple' => true, 'max_files' => 3]);

    $this->actingAs(imageMultipleOfficer())
        ->post(route('forms.render.submit', $form->route_name), [
            'photos' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        ])
        ->assertSessionHasNoErrors();

    $payload = FormSubmission::query()->where('form_id', $form->id)->firstOrFail()->payload;

    expect($payload['photos'])->toBeArray()->toHaveCount(2);
    foreach ($payload['photos'] as $path) {
        expect(Storage::disk('public')->exists($path))->toBeTrue();
    }
});

it('keeps single image fields stored as one path', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = imageMultipleForm(['multiple' => false, 'max_files' => 5]);

    $this->actingAs(imageMultipleOfficer())
        ->post(route('forms.render.submit', $form->route_name), [
            'photos' => UploadedFile::fake()->image('single.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $payload = FormSubmission::query()->where('form_id', $form->id)->firstOrFail()->payload;

    expect($payload['photos'])->toBeString();
    expect(Storage::disk('public')->exists($payload['photos']))->toBeTrue();
});

it('builds docx image data as arrays only for multi image fields', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $multiOne = UploadedFile::fake()->image('multi-one.jpg')->store('uploads', 'public');
    $multiTwo = UploadedFile::fake()->image('multi-two.jpg')->store('uploads', 'public');
    $single = UploadedFile::fake()->image('single.jpg')->store('uploads', 'public');
    $form = Form::query()->create(['name' => 'Docx Data', 'route_name' => 'docx-data']);
    $fields = collect([
        new FormDescription(['field_key' => 'photos', 'field_type' => 'image', 'field_options' => ['multiple' => true]]),
        new FormDescription(['field_key' => 'cover', 'field_type' => 'image', 'field_options' => ['multiple' => false]]),
        new FormDescription(['field_key' => 'set', 'field_type' => 'multi-image']),
    ]);

    $data = app(DocxTemplateData::class)->build([
        'photos' => [$multiOne, $multiTwo],
        'cover' => $single,
        'set' => [$multiOne, $multiTwo],
    ], $fields);

    expect($data['images']['photos'])->toBeArray()->toHaveCount(2)
        ->and($data['images']['set'])->toBeArray()->toHaveCount(2)
        ->and($data['images']['cover'])->toBeString();
});

it('populates all images for a plain docx placeholder', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $template = imageDocxTemplate(false);
    $paths = [storedImagePath('plain-one.jpg'), storedImagePath('plain-two.jpg')];

    $output = app(DocxTemplateService::class)->populate($template, [], ['photos' => $paths]);

    expect(docxImageXmlCount($output))->toBe(2)
        ->and(docxText($output))->not->toContain('{{')->not->toContain('${');
});

it('populates all images for a repeating table-row docx placeholder', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $template = imageDocxTemplate(true);
    $paths = [storedImagePath('row-one.jpg'), storedImagePath('row-two.jpg'), storedImagePath('row-three.jpg')];

    $output = app(DocxTemplateService::class)->populate($template, [], ['photos' => $paths]);

    expect(docxImageXmlCount($output))->toBe(3)
        ->and(docxText($output))->not->toContain('{{')->not->toContain('${');
});
