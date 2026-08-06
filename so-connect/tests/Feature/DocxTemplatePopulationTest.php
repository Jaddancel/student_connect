<?php

use App\Models\Form;
use App\Models\Template;
use App\Services\DocxTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * A form + active template pair backed by the sample .docx on the fake disk.
 */
function docxTemplateFixture(): Template
{
    Storage::fake('public');

    $form = Form::query()->create([
        'name' => 'Event Attendance',
        'route_name' => 'forms.event-attendance',
    ]);

    $relativePath = 'form-templates/'.$form->getKey().'/attendance.docx';

    Storage::disk('public')->put($relativePath, '');
    makeDocxTemplate(Storage::disk('public')->path($relativePath));

    return Template::query()->create([
        'form_id' => $form->getKey(),
        'template_name' => 'Attendance Sheet',
        'docx_path' => $relativePath,
        'version' => 1,
        'is_active' => true,
    ]);
}

/** Populate the fixture and read the generated document's text back. */
function populatedText(Template $template, array $data): string
{
    $path = app(DocxTemplateService::class)->populate($template, $data);

    expect($path)->toEndWith('.docx')
        ->and(is_file($path))->toBeTrue();

    return docxText($path);
}

it('fills plain placeholders', function () {
    $text = populatedText(docxTemplateFixture(), [
        'organization_name' => 'Computer Science Society',
        'event_title' => 'General Assembly',
        'event_date' => 'March 4, 2026',
    ]);

    expect($text)->toContain('Computer Science Society Attendance Sheet')
        ->toContain('Event: General Assembly')
        ->toContain('Date: March 4, 2026');
});

it('never leaves an unresolved placeholder in the output', function () {
    $text = populatedText(docxTemplateFixture(), [
        'organization_name' => 'Computer Science Society',
    ]);

    // Blanked rather than printed literally — a template with no data behind a
    // field must not ship "{{unused_field}}" to whoever receives the document.
    expect($text)->toContain('Unmapped:')
        ->not->toContain('{{')
        ->not->toContain('${');
});

it('clones a table row per repeating value, filling every column', function () {
    $text = populatedText(docxTemplateFixture(), [
        'attendee_name' => ['Ana Reyes', 'Ben Cruz', 'Cara Lim'],
        'attendee_course' => ['BSCS', 'BSIT', 'BSCS'],
    ]);

    expect($text)->toContain('Ana Reyes')
        ->toContain('Ben Cruz')
        ->toContain('Cara Lim')
        ->toContain('BSIT');

    // The header row survives and each attendee lands on their own row.
    expect(substr_count($text, 'BSCS'))->toBe(2);
});

it('joins a repeating placeholder outside a table into one multi-line run', function () {
    $text = populatedText(docxTemplateFixture(), [
        'notes' => ['Bring your ID.', 'Snacks provided.'],
    ]);

    expect($text)->toContain('Bring your ID.')
        ->toContain('Snacks provided.');
});

it('resolves PhpWord legacy ${} placeholders', function () {
    $text = populatedText(docxTemplateFixture(), [
        'adviser_name' => 'Dr. Elena Santos',
    ]);

    expect($text)->toContain('Adviser: Dr. Elena Santos');
});

it('escapes XML-special characters instead of corrupting the document', function () {
    // The generated file has to stay a readable .docx — an unescaped & or <
    // would break word/document.xml and make it unopenable.
    $text = populatedText(docxTemplateFixture(), [
        'event_title' => 'Research & Development <Summit>',
    ]);

    expect($text)->toContain('Research & Development <Summit>');
});

it('formats non-string values', function () {
    $text = populatedText(docxTemplateFixture(), [
        'event_title' => true,
        'event_date' => new DateTimeImmutable('2026-03-04'),
        'organization_name' => ['Alpha', 'Beta'],
    ]);

    expect($text)->toContain('Event: Yes')
        ->toContain('Date: March 4, 2026')
        ->toContain('Alpha, Beta');
});

it('fails clearly when the template file is missing from disk', function () {
    $template = docxTemplateFixture();
    Storage::disk('public')->delete($template->docx_path);

    expect(fn () => app(DocxTemplateService::class)->populate($template, []))
        ->toThrow(RuntimeException::class);
});

it('generates a .docx through the endpoint', function () {
    $template = docxTemplateFixture();

    $response = $this->actingAs(recordsUser(2))
        ->post(route('admin.templates.generate', $template), [
            'data' => [
                'organization_name' => 'Computer Science Society',
                'attendee_name' => ['Ana Reyes', 'Ben Cruz'],
            ],
        ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertHeader('Content-Disposition', 'attachment; filename="attendance-sheet.docx"');

    // A .docx is a zip — "PK" proves real document bytes came back.
    expect($response->getContent())->toStartWith('PK');
});

it('requires field data on the generate endpoint', function () {
    $template = docxTemplateFixture();

    $this->actingAs(recordsUser(2))
        ->post(route('admin.templates.generate', $template), [])
        ->assertSessionHasErrors('data');
});

it('blocks non-admins from generating', function () {
    $template = docxTemplateFixture();

    $this->actingAs(recordsUser(3))
        ->post(route('admin.templates.generate', $template), ['data' => ['event_title' => 'X']])
        ->assertForbidden();
});
