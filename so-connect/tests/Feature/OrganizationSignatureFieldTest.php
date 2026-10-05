<?php

use App\Forms\DocxTemplateData;
use App\Forms\FieldType;
use App\Forms\PdfTemplateRenderer;
use App\Models\Form;
use App\Models\Officer;
use App\Models\Organization;
use App\Models\Template;
use App\Models\User;
use App\Reports\ReportDocxRenderer;
use App\Reports\ReportPalette;
use App\Services\DocxTemplateService;
use App\Services\FormPrintTemplateService;
use App\Services\OnlyOfficeService;
use App\Support\OrganizationField;
use App\Support\UniversalField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

uses(RefreshDatabase::class);

function signatureOfficer(Organization $organization, string $position, ?string $path, string $role = 'officer'): User
{
    $user = recordsUser(3, ['signature_path' => $path]);
    Officer::query()->create([
        'organization' => $organization->getKey(),
        'user' => $user->getKey(),
        'role' => $role,
        'position' => $position,
    ]);

    return $user;
}

it('registers signatures for every defined position except Others', function () {
    $keys = [];
    foreach (FieldType::POSITION_OPTIONS as $position) {
        if ($position === 'Others') {
            continue;
        }
        $key = 'org_'.strtolower($position).'_signature';
        $keys[] = $key;
        expect(UniversalField::get($key))->toMatchArray([
            'label' => 'Organization '.$position.' Signature',
            'type' => FieldType::SIGNATURE,
            'source' => 'org',
            'group' => 'organization',
        ]);
    }

    expect(array_values(array_filter(
        UniversalField::keysBySource('org'),
        fn ($key) => str_ends_with($key, '_signature'),
    )))->toEqualCanonicalizing($keys)
        ->and(UniversalField::has('org_others_signature'))->toBeFalse();
});

it('resolves the latest position holder signature only from the selected organization', function (string $position) {
    $organization = recordsOrganization('Selected');
    $other = recordsOrganization('Other');
    $key = 'org_'.strtolower($position).'_signature';

    signatureOfficer($organization, $position, 'signatures/old.png');
    signatureOfficer($organization, strtolower($position), 'signatures/current.png', $position === 'President' ? 'president' : 'officer');
    signatureOfficer($other, $position, 'signatures/other.png');
    signatureOfficer($organization, 'Others', 'signatures/others.png');
    signatureOfficer($organization, $position, 'signatures/member.png', 'member');

    expect(OrganizationField::value($organization, $key))->toBe('signatures/current.png')
        ->and(OrganizationField::value($other, $key))->toBe('signatures/other.png')
        ->and(OrganizationField::value(null, $key))->toBeNull()
        ->and(OrganizationField::value($organization, 'org_others_signature'))->toBeNull();

    signatureOfficer($organization, $position, null);
    expect(OrganizationField::value($organization, $key))->toBeNull();
})->with(['President', 'Treasurer', 'Auditor', 'Secretary']);

it('leaves signatures blank when an organization has no position holder or profile', function () {
    $organization = recordsOrganization('Missing signatures');
    expect(OrganizationField::value($organization, 'org_secretary_signature'))->toBeNull();

    $user = signatureOfficer($organization, 'Secretary', 'signatures/secretary.png');
    $user->profile()->first()->delete();
    expect(OrganizationField::value($organization, 'org_secretary_signature'))->toBeNull();
});

it('offers the same organization signature tokens in both Step 2 palettes', function () {
    Storage::fake('public');
    config(['onlyoffice.jwt_secret' => str_repeat('s', 40)]);
    $form = Form::query()->create(['name' => 'Signature form', 'route_name' => 'signature-form']);
    $template = app(FormPrintTemplateService::class)->resolve($form);
    $token = app(OnlyOfficeService::class)->sign([
        'tid' => (int) $template->getKey(),
        'purpose' => 'plugin',
        'exp' => now()->addMinutes(30)->getTimestamp(),
    ]);
    $html = $this->get(route('onlyoffice.plugin', ['form' => $form, 'token' => $token]))->assertOk()->getContent();
    preg_match('~<script id="token-data" type="application/json">(.*?)</script>~s', $html, $matches);
    $formTokens = collect(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR))->keyBy('key');
    $reportTokens = collect(ReportPalette::tokens(['tokens' => []]))->keyBy('key');

    foreach (['president', 'treasurer', 'auditor', 'secretary'] as $position) {
        $key = 'profile.org_'.$position.'_signature';
        expect($formTokens[$key])->toMatchArray(['group' => 'Organization', 'icon' => FieldType::catalog()[FieldType::SIGNATURE]['icon']])
            ->and($reportTokens[$key])->toBe($formTokens[$key]);
    }
    expect($formTokens->has('profile.org_others_signature'))->toBeFalse()
        ->and($reportTokens->has('profile.org_others_signature'))->toBeFalse();
});

it('prints saved officer and personal signatures as images in forms and reports', function () {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
    $organization = recordsOrganization('Signature images');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZWQAAAAASUVORK5CYII=');
    Storage::disk('public')->put('signatures/saved.png', $png);
    $user = signatureOfficer($organization, 'President', 'signatures/saved.png', 'president');
    foreach (['Treasurer', 'Auditor', 'Secretary'] as $position) {
        signatureOfficer($organization, $position, 'signatures/saved.png');
    }
    $profile = $user->profile()->first();
    $built = app(DocxTemplateData::class)->build([], collect(), $profile, $organization);
    $keys = ['signature', 'org_president_signature', 'org_treasurer_signature', 'org_auditor_signature', 'org_secretary_signature'];
    $word = new PhpWord;
    $section = $word->addSection();
    foreach ($keys as $key) {
        expect($built['images']['profile.'.$key])->toBe(Storage::disk('public')->path('signatures/saved.png'))
            ->and($built['values'])->not->toHaveKey('profile.'.$key);
        $section->addText('{{profile.'.$key.'}}');
        $html = app(PdfTemplateRenderer::class)->render(
            '<p><span data-universal="'.$key.'">Signature</span></p>',
            [], collect(), 'public', $profile, $organization,
        );
        expect($html)->toContain('<img', 'data:image/png;base64,')->not->toContain('signatures/saved.png');
    }

    Storage::disk('public')->put('signature-template.docx', '');
    IOFactory::createWriter($word, 'Word2007')->save(Storage::disk('public')->path('signature-template.docx'));
    $form = Form::query()->create(['name' => 'Signatures', 'route_name' => 'signatures']);
    $template = Template::query()->create([
        'form_id' => $form->getKey(), 'template_name' => 'Signatures',
        'docx_path' => 'signature-template.docx', 'version' => 1, 'is_active' => true,
    ]);
    $paths = [];
    try {
        $paths[] = app(DocxTemplateService::class)->populate($template, $built['values'], $built['images']);
        $paths[] = app(ReportDocxRenderer::class)->render($template, [], $user, $organization);
        foreach ($paths as $path) {
            $zip = new ZipArchive;
            expect($zip->open($path))->toBeTrue();
            $xml = $zip->getFromName('word/document.xml');
            $dom = new DOMDocument;
            $dom->loadXML($xml);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');
            $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $images = $xpath->query('//v:imagedata[@r:id]');
            expect($images->length)->toBe(count($keys));
            $relationships = $zip->getFromName('word/_rels/document.xml.rels');
            $relationsDom = new DOMDocument;
            $relationsDom->loadXML($relationships);
            $relationsXpath = new DOMXPath($relationsDom);
            $relationsXpath->registerNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');
            $imageRelations = $relationsXpath->query('//p:Relationship[contains(@Type, "/image")]');
            expect($imageRelations->length)->toBeGreaterThan(0);
            foreach ($imageRelations as $relation) {
                expect($zip->getFromName('word/'.$relation->getAttribute('Target')))->toBe($png);
            }
            $zip->close();
            expect($xml)->not->toContain('signatures/saved.png', '{{profile.', '${profile');
        }
    } finally {
        foreach ($paths as $path) {
            File::deleteDirectory(dirname($path));
        }
    }
});

it('prints missing stored signature files blank rather than as paths', function () {
    Storage::fake('public');
    $organization = recordsOrganization('Missing image');
    signatureOfficer($organization, 'Treasurer', 'signatures/missing.png');
    $built = app(DocxTemplateData::class)->build([], collect(), null, $organization, 'public');
    expect($built['values']['profile.org_treasurer_signature'])->toBe('')
        ->and($built['images'])->not->toHaveKey('profile.org_treasurer_signature');
    $html = app(PdfTemplateRenderer::class)->render(
        '<p><span data-universal="org_treasurer_signature">Signature</span></p>',
        [], collect(), 'public', null, $organization,
    );
    expect($html)->not->toContain('<img', 'signatures/missing.png', 'data-universal');
});
