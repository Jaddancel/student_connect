<?php

use App\Models\Form;
use App\Models\Template;
use App\Models\User;
use App\Services\DocxTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * A published, active form, optionally backed by an active Step 2 template.
 * `$templateFileOnDisk = false` leaves the row pointing at a .docx that was
 * never written — the stale-storage case.
 */
function directoryForm(array $attributes = [], bool $withTemplate = true, bool $templateFileOnDisk = true): Form
{
    Storage::fake('public');

    $form = Form::query()->create(array_merge([
        'name' => 'Directory Form '.\Illuminate\Support\Str::random(6),
        'route_name' => 'directory-'.\Illuminate\Support\Str::random(6),
        'is_published' => true,
        'is_active' => true,
    ], $attributes));

    if ($withTemplate) {
        $relativePath = 'form-templates/'.$form->getKey().'/blank.docx';

        if ($templateFileOnDisk) {
            Storage::disk('public')->put($relativePath, '');
            makeDocxTemplate(Storage::disk('public')->path($relativePath));
        }

        Template::query()->create([
            'form_id' => $form->getKey(),
            'template_name' => $form->name,
            'docx_path' => $relativePath,
            'version' => 1,
            'is_active' => true,
        ]);
    }

    return $form;
}

/** A user-type-3 account holding an officer role — the directory's audience. */
function directoryOfficer(): User
{
    $user = recordsUser(3);
    $organization = recordsOrganization('Directory Org '.\Illuminate\Support\Str::random(5));

    DB::table('organization_officers')->insert([
        'role' => 'officer',
        'organization' => (int) $organization->getKey(),
        'user' => (int) $user->getKey(),
        'yearterm' => null,
        'member_since' => now(),
        'registered_at' => now(),
        'reassigned_at' => now(),
    ]);

    return $user;
}

/** Names the directory handed to the view, in display order. */
function directoryNames($response): array
{
    return collect($response->viewData('forms'))->pluck('name')->all();
}

// ── Directory listing ───────────────────────────────────────────────────────

it('lists a published form with a usable template, linked to its blank PDF', function () {
    $form = directoryForm(['name' => 'Clearance Form']);

    $response = $this->actingAs(directoryOfficer())->get(route('forms.directory'));

    $response->assertOk();
    $forms = collect($response->viewData('forms'));

    expect($forms)->toHaveCount(1)
        ->and($forms->first()['name'])->toBe('Clearance Form')
        ->and($forms->first()['url'])->toBe(route('forms.blank-pdf', $form->route_name));
});

it('omits forms that have no stored template', function () {
    directoryForm(['name' => 'Template-less'], withTemplate: false);
    directoryForm(['name' => 'Printable']);

    $response = $this->actingAs(directoryOfficer())->get(route('forms.directory'));

    expect(directoryNames($response))->toBe(['Printable']);
});

it('omits unpublished and inactive forms', function () {
    directoryForm(['name' => 'Unpublished', 'is_published' => false]);
    directoryForm(['name' => 'Inactive', 'is_active' => false]);
    directoryForm(['name' => 'Live']);

    $response = $this->actingAs(directoryOfficer())->get(route('forms.directory'));

    expect(directoryNames($response))->toBe(['Live']);
});

it('omits forms whose template file is missing from storage', function () {
    directoryForm(['name' => 'Stale Storage'], templateFileOnDisk: false);

    $response = $this->actingAs(directoryOfficer())->get(route('forms.directory'));

    expect(directoryNames($response))->toBe([]);
});

it('shows an empty directory to users without an officer or president role', function () {
    directoryForm();

    $response = $this->actingAs(recordsUser(3))->get(route('forms.directory'));

    $response->assertOk();
    expect(directoryNames($response))->toBe([]);
});

// ── Blank PDF endpoint ──────────────────────────────────────────────────────

it('streams the blank PDF inline to an officer and cleans up its scratch files', function () {
    $form = directoryForm(['name' => 'Clearance Form']);

    $scratchDir = null;

    // LibreOffice isn't available in the dev shell, so the populate/convert
    // bridge is mocked — but with real files on disk, so the controller's
    // read-and-clean-up path runs for real.
    $this->mock(DocxTemplateService::class, function ($mock) use (&$scratchDir) {
        $mock->shouldReceive('populate')->once()->andReturnUsing(function ($template, $data) use (&$scratchDir) {
            expect($data)->toBe([]);

            $scratchDir = storage_path('app/tmp/blank-test-'.bin2hex(random_bytes(4)));
            File::ensureDirectoryExists($scratchDir);
            $docxPath = $scratchDir.'/blank.docx';
            File::put($docxPath, 'docx-bytes');

            return $docxPath;
        });
        $mock->shouldReceive('toPdf')->once()->andReturnUsing(function ($docxPath) {
            $pdfPath = dirname($docxPath).'/blank.pdf';
            File::put($pdfPath, '%PDF-1.4 blank');

            return $pdfPath;
        });
    });

    $response = $this->actingAs(directoryOfficer())->get(route('forms.blank-pdf', $form->route_name));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))
        ->toStartWith('inline;')
        ->toContain('clearance-form-blank.pdf')
        ->and($response->getContent())->toStartWith('%PDF')
        ->and($scratchDir)->not->toBeNull()
        ->and(is_dir($scratchDir))->toBeFalse();
});

it('rejects guests from the blank PDF endpoint', function () {
    $form = directoryForm();

    $this->get(route('forms.blank-pdf', $form->route_name))->assertForbidden();
});

it('rejects members without an officer or president role', function () {
    $form = directoryForm();

    $this->actingAs(recordsUser(3))
        ->get(route('forms.blank-pdf', $form->route_name))
        ->assertForbidden();
});

it('rejects admins, who author forms rather than fill them', function () {
    $form = directoryForm();

    $this->actingAs(recordsUser(2))
        ->get(route('forms.blank-pdf', $form->route_name))
        ->assertForbidden();
});

it('404s when the form has no stored template', function () {
    $form = directoryForm(withTemplate: false);

    $this->actingAs(directoryOfficer())
        ->get(route('forms.blank-pdf', $form->route_name))
        ->assertNotFound();
});

it('404s when the form is unpublished', function () {
    $form = directoryForm(['is_published' => false]);

    $this->actingAs(directoryOfficer())
        ->get(route('forms.blank-pdf', $form->route_name))
        ->assertNotFound();
});

it('404s when the form is inactive', function () {
    $form = directoryForm(['is_active' => false]);

    $this->actingAs(directoryOfficer())
        ->get(route('forms.blank-pdf', $form->route_name))
        ->assertNotFound();
});

it('404s when the template file is missing from storage', function () {
    $form = directoryForm(templateFileOnDisk: false);

    $this->actingAs(directoryOfficer())
        ->get(route('forms.blank-pdf', $form->route_name))
        ->assertNotFound();
});
