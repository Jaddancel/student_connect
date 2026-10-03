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

/** A template whose table data-row cells use dotted activity-table tokens. */
function activityDocxTemplate(): Template
{
    Storage::fake('public');

    $form = Form::query()->create([
        'name' => 'Workplan', 'route_name' => 'forms.workplan-'.\Illuminate\Support\Str::random(5),
    ]);
    $relativePath = 'form-templates/'.$form->getKey().'/wp.docx';
    Storage::disk('public')->put($relativePath, '');
    $path = Storage::disk('public')->path($relativePath);

    $phpWord = new \PhpOffice\PhpWord\PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Org: {{org}}');
    $table = $section->addTable(['borderSize' => 6]);
    $table->addRow();
    $table->addCell(4000)->addText('Activity', ['bold' => true]);
    $table->addCell(3000)->addText('Date', ['bold' => true]);
    $table->addCell(3000)->addText('Org', ['bold' => true]);
    $table->addRow();
    $table->addCell(4000)->addText('{{wp_activities.title#}}');
    $table->addCell(3000)->addText('{{wp_activities.target_date#}}');
    $table->addCell(3000)->addText('{{org}}');
    \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($path);

    return Template::query()->create([
        'form_id' => $form->getKey(), 'template_name' => 'Workplan',
        'docx_path' => $relativePath, 'version' => 1, 'is_active' => true,
    ]);
}

it('clones a table row per activity using dotted column tokens', function () {
    $text = populatedText(activityDocxTemplate(), [
        'wp_activities.title' => ['Acquaintance Party', 'Tree Planting', 'Leadership Seminar'],
        'wp_activities.target_date' => ['Sep 12, 2026', 'Oct 03, 2026', 'Nov 20, 2026'],
        'org' => 'Robotics Society',
    ]);

    expect($text)->toContain('Acquaintance Party')->toContain('Tree Planting')->toContain('Leadership Seminar')
        ->toContain('Sep 12, 2026')->toContain('Oct 03, 2026')->toContain('Nov 20, 2026')
        ->not->toContain('{{')->not->toContain('${');

    // The non-repeating sibling repeats down each cloned row (plus the header line).
    expect(substr_count($text, 'Robotics Society'))->toBeGreaterThanOrEqual(4);
});

it('leaves one blank row and no literal tokens when there are no activities', function () {
    $text = populatedText(activityDocxTemplate(), [
        'wp_activities.title' => [],
        'wp_activities.target_date' => [],
        'org' => 'Robotics Society',
    ]);

    expect($text)->not->toContain('{{')->not->toContain('${');
});

it('emits dotted parallel arrays and a base fallback for an activity-table field', function () {
    $field = new \App\Models\Form\FormDescription([
        'field_key' => 'wp_activities',
        'field_type' => 'activity-table',
        'field_options' => ['columns' => [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['key' => 'target_date', 'label' => 'Date', 'type' => 'date'],
        ]],
    ]);

    $payload = ['wp_activities' => [
        ['title' => 'A', 'target_date' => 'Sep 12, 2026'],
        ['title' => 'B', 'target_date' => 'Oct 03, 2026'],
    ]];

    $values = app(\App\Forms\DocxTemplateData::class)->build($payload, collect([$field]))['values'];

    expect($values['wp_activities.title'])->toBe(['A', 'B'])
        ->and($values['wp_activities.target_date'])->toBe(['Sep 12, 2026', 'Oct 03, 2026'])
        // A bare {{wp_activities}} / {{wp_activities#}} falls back to the first column.
        ->and($values['wp_activities'])->toBe(['A', 'B']);
});

it('emits dotted parallel arrays, a row total and a base fallback for a table-input field', function () {
    $field = new \App\Models\Form\FormDescription([
        'field_key' => 'expenses',
        'field_type' => 'table-input',
        'field_options' => [
            'columns' => [
                ['key' => 'item', 'label' => 'Item', 'type' => 'text'],
                ['key' => 'price', 'label' => 'Price', 'type' => 'number'],
                ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ],
            'row_total' => ['key' => 'line_total', 'label' => 'Total', 'multiply' => ['price', 'qty']],
        ],
    ]);

    $payload = ['expenses' => [
        ['item' => 'Pens', 'price' => '10', 'qty' => '2', 'line_total' => '20'],
        ['item' => 'Paper', 'price' => '5', 'qty' => '4', 'line_total' => '20'],
    ]];

    $values = app(\App\Forms\DocxTemplateData::class)->build($payload, collect([$field]))['values'];

    expect($values['expenses.item'])->toBe(['Pens', 'Paper'])
        ->and($values['expenses.price'])->toBe(['10', '5'])
        ->and($values['expenses.qty'])->toBe(['2', '4'])
        // The per-row computed column prints alongside the declared columns.
        ->and($values['expenses.line_total'])->toBe(['20', '20'])
        // A bare {{expenses}} / {{expenses#}} falls back to the first column.
        ->and($values['expenses'])->toBe(['Pens', 'Paper']);
});
