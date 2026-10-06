<?php

use App\Forms\DocxTemplateData;
use App\Forms\FieldType;
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

function imageMultipleForm(array $options = ['max_files' => 2], bool $required = false, string $type = 'multi-image'): Form
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
        'field_type' => $type,
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

it('validates max count and mimes for photo set fields', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = imageMultipleForm(['max_files' => 2], true);
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

it('stores photo set uploads as an array of paths', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = imageMultipleForm(['max_files' => 3]);

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

it('keeps image fields single even with a legacy multiple option', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = imageMultipleForm(['multiple' => true, 'max_files' => 5], false, 'image');

    expect(FieldType::isMultiImage('image'))->toBeFalse()
        ->and(FieldType::isMultiImage('multi-image'))->toBeTrue();

    $this->actingAs(imageMultipleOfficer())
        ->post(route('forms.render.submit', $form->route_name), [
            'photos' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
        ])
        ->assertSessionHasErrors('photos');
});

it('keeps single image fields stored as one path', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $form = imageMultipleForm([], false, 'image');

    $this->actingAs(imageMultipleOfficer())
        ->post(route('forms.render.submit', $form->route_name), [
            'photos' => UploadedFile::fake()->image('single.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $payload = FormSubmission::query()->where('form_id', $form->id)->firstOrFail()->payload;

    expect($payload['photos'])->toBeString();
    expect(Storage::disk('public')->exists($payload['photos']))->toBeTrue();
});

it('builds docx image data as arrays only for photo set fields', function () {
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

    expect($data['images']['photos'])->toBe(Storage::disk('public')->path($multiOne))
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

/**
 * A template whose body is built by $build(section); $build gets the PhpWord section.
 */
function imageLayoutTemplate(callable $build): Template
{
    $form = Form::query()->create([
        'name' => 'Image Layout',
        'route_name' => 'image-layout-'.Str::lower(Str::random(8)),
    ]);
    $relativePath = 'form-templates/'.$form->getKey().'/layout.docx';
    Storage::disk('public')->put($relativePath, '');

    $phpWord = new PhpOffice\PhpWord\PhpWord;
    $build($phpWord->addSection());
    PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save(Storage::disk('public')->path($relativePath));

    return Template::query()->create([
        'form_id' => $form->getKey(),
        'template_name' => 'Layout Template',
        'docx_path' => $relativePath,
        'version' => 1,
        'is_active' => true,
    ]);
}

/**
 * Pictures per table row: one entry per row, one entry per cell holding the
 * cell's picture size as [width pt, height pt] (null for an empty cell).
 *
 * @return array<int, array<int, array{0: float, 1: float}|null>>
 */
function docxPictureGrid(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);
    $dom = new DOMDocument;
    $dom->loadXML((string) $zip->getFromName('word/document.xml'));
    $zip->close();
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

    $grid = [];
    foreach ($xpath->query('//w:tr') as $row) {
        $cells = [];
        foreach ($xpath->query('w:tc', $row) as $cell) {
            $shape = $xpath->query('.//v:shape', $cell)->item(0);
            if ($shape === null) {
                $cells[] = null;

                continue;
            }
            preg_match('/width:([\d.]+)pt;height:([\d.]+)pt/', $shape->getAttribute('style'), $m);
            $cells[] = [round((float) $m[1], 1), round((float) $m[2], 1)];
        }
        $grid[] = $cells;
    }

    return $grid;
}

it('fits a single image inside its cell less the table cell margins', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $template = imageLayoutTemplate(function ($section) {
        $table = $section->addTable(['cellMargin' => 100]);
        $table->addRow(1440, ['exactHeight' => true]);
        $table->addCell(4000)->addText('{{signature}}');
    });

    $output = app(DocxTemplateService::class)->populate($template, [], ['signature' => storedImagePath('sig.png')]);

    // Box: 4000 - 2×100 = 3800 wide, 1440 - 2×100 - 60 (baseline) = 1180 tall
    // twips. An 80×60 image is height-bound: 1180 twips = 59pt tall, 4:3 wide.
    expect(docxPictureGrid($output))->toBe([[[78.7, 59.0]]])
        ->and(docxText($output))->not->toContain('{{');
});

it('fits a single image to the cell width when the row height is not set', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $template = imageLayoutTemplate(function ($section) {
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(3000)->addText('{{cover}}');
    });

    $output = app(DocxTemplateService::class)->populate($template, [], ['cover' => storedImagePath('cover.jpg')]);

    // Word's default 108-twip left/right inset: 3000 - 216 = 2784 twips = 139.2pt.
    expect(docxPictureGrid($output))->toBe([[[139.2, 104.4]]]);
});

it('spreads a photo set across the row cells and adds rows when the columns run out', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $template = imageLayoutTemplate(function ($section) {
        $table = $section->addTable();
        $table->addRow(2000, ['exactHeight' => true]);
        $table->addCell(2000)->addText('{{photos}}');
        $table->addCell(2000);
        $table->addCell(2000);
    });
    $paths = array_map(fn ($i) => storedImagePath("set-$i.jpg"), range(1, 5));

    $output = app(DocxTemplateService::class)->populate($template, [], ['photos' => $paths]);

    // Each cell: 2000 - 216 = 1784 twips wide → 89.2pt; 4:3 → 66.9pt tall.
    $picture = [89.2, 66.9];
    expect(docxPictureGrid($output))->toBe([
        [$picture, $picture, $picture],
        [$picture, $picture, null],
    ])->and(docxImageXmlCount($output))->toBe(5)
        ->and(docxText($output))->not->toContain('{{');

    $xml = (function () use ($output) {
        $zip = new ZipArchive;
        $zip->open($output);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    })();
    // The cloned row keeps the template row's fixed height.
    expect(substr_count($xml, 'w:hRule="exact"'))->toBe(2);
});

it('keeps a photo set in a single-column row to one picture per row', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $template = imageLayoutTemplate(function ($section) {
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('{{photos#}}');
    });
    $paths = [storedImagePath('col-one.jpg'), storedImagePath('col-two.jpg')];

    $output = app(DocxTemplateService::class)->populate($template, [], ['photos' => $paths]);

    expect(docxPictureGrid($output))->toBe([[[89.2, 66.9]], [[89.2, 66.9]]]);
});

/**
 * A template whose body is exactly $bodyXml (WordprocessingML, `w:` prefix).
 */
function rawBodyTemplate(string $bodyXml): Template
{
    $template = imageLayoutTemplate(fn ($section) => $section->addText('BODY'));
    $path = Storage::disk('public')->path($template->docx_path);

    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $xml = (string) preg_replace('~(<w:body>).*?(<w:sectPr)~s', '$1'.$bodyXml.'$2', $xml);
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();

    return $template;
}

it('honours OnlyOffice per-cell margins and ignores its one-line minimum row height', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    // As OnlyOffice saves it: cell-level tcMar, and an "at least" 276-twip row.
    $cell = fn (string $text) => '<w:tc><w:tcPr><w:tcMar><w:left w:w="0" w:type="dxa"/><w:top w:w="0" w:type="dxa"/>'
        .'<w:right w:w="200" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/></w:tcMar><w:tcW w:w="3200" w:type="dxa"/></w:tcPr>'
        .'<w:p><w:pPr><w:spacing w:after="160" w:line="240" w:lineRule="exact"/><w:ind/></w:pPr><w:r><w:t>'.$text.'</w:t></w:r></w:p></w:tc>';
    $template = rawBodyTemplate(
        '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblCellMar><w:left w:w="500" w:type="dxa"/></w:tblCellMar></w:tblPr>'
        .'<w:tblGrid><w:gridCol w:w="3200"/></w:tblGrid>'
        .'<w:tr><w:trPr><w:trHeight w:val="276"/></w:trPr>'.$cell('{{signature}}').'</w:tr></w:tbl>'
        .'<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/></w:tblPr><w:tblGrid><w:gridCol w:w="3200"/></w:tblGrid>'
        .'<w:tr><w:trPr><w:trHeight w:val="1260"/></w:trPr>'.$cell('{{profile.signature}}').'</w:tr></w:tbl>'
    );
    $signature = storedImagePath('signature.png');

    $output = app(DocxTemplateService::class)->populate($template, [], [
        'signature' => $signature,
        'profile.signature' => $signature,
    ]);

    // Width 3200 - 0 - 200 = 3000 twips = 150pt (the table's 500 left margin
    // is overridden by the cell's 0). Row one is unsized → width-fitted.
    // Row two is sized: 1260 - 60 = 1200 twips = 60pt tall, 4:3 → 80pt wide.
    expect(docxPictureGrid($output))->toBe([[[150.0, 112.5]], [[80.0, 60.0]]]);

    $zip = new ZipArchive;
    $zip->open($output);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    // The picture paragraph drops its spacing and exact line height (which
    // would clip the picture), keeping the schema order of w:spacing/w:ind.
    expect($xml)->not->toContain('w:lineRule="exact"')
        ->and($xml)->not->toContain('w:after="160"')
        ->and(substr_count($xml, '<w:ind/>'))->toBe(2);
});

it('renders a multi-file picker only for photo set fields', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $officer = imageMultipleOfficer();
    $set = imageMultipleForm(['max_files' => 4]);
    $image = imageMultipleForm(['multiple' => true], false, 'image');

    $setHtml = $this->actingAs($officer)->get(route('forms.render', $set->route_name))->assertOk()->getContent();
    $imageHtml = $this->actingAs($officer)->get(route('forms.render', $image->route_name))->assertOk()->getContent();

    expect($setHtml)->toContain('name="photos[]"')->toContain('up to 4 images')
        ->and($imageHtml)->toContain('name="photos"')->not->toContain('name="photos[]"');
});
