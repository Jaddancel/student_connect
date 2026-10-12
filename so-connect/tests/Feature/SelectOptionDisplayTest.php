<?php

use App\Forms\PdfTemplateRenderer;
use App\Helpers\FormTemplateHelper;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Services\DocumentGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

function selectDisplaySubmission(string $type, mixed $value, bool $multiple = false): FormSubmission
{
    $form = Form::query()->create([
        'name' => 'Option Labels',
        'route_name' => 'option-labels',
        'is_active' => true,
        'is_published' => true,
        'pdf_template' => [
            'html' => '<p>Scope: <span data-field="scope">Scope</span></p>',
            'page' => ['size' => 'a4', 'orientation' => 'portrait'],
        ],
    ]);

    FormDescription::query()->create([
        'form_id' => $form->id,
        'field_key' => 'scope',
        'field_label' => 'Scope',
        'field_type' => $type,
        'field_order' => 1,
        'field_options' => [
            'multiple' => $multiple,
            'options' => [
                ['value' => 'college', 'label' => 'College'],
                ['value' => 'option_6', 'label' => 'Regional'],
                ['value' => 'option_7', 'label' => 'National'],
                ['value' => '0', 'label' => '0'],
            ],
        ],
    ]);

    return FormSubmission::query()->create([
        'form_id' => $form->id,
        'payload' => ['scope' => $value],
        'submitted_at' => now(),
    ]);
}

it('shows option labels on both request review pages without changing stored keys', function (string $page, string $type, mixed $value, bool $multiple, string $expected) {
    $submission = selectDisplaySubmission($type, $value, $multiple);
    $form = $submission->form;
    $request = ActionRequest::query()->create([
        'form_id' => $form->id,
        'action_type' => FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
        'action' => 'option label review',
        'payload' => ['submission_id' => $submission->getKey(), 'form_id' => $form->id],
        'requested_at' => now(),
    ]);
    $url = $page === 'activity'
        ? route('admin.activity-requests.show', $request->getKey())
        : route('admin.form-requests.show', [$form, $request->getKey()]);

    $response = $this->actingAs(recordsUser(2))->get($url)->assertOk();
    $response->assertSee('>'.$expected.'</p>', false);
    foreach (['option_6', 'option_7'] as $key) {
        $response->assertDontSee($key);
    }

    expect($submission->fresh()->payload['scope'])->toBe($value);
})->with(['activity', 'form'])->with([
    'single select' => ['select', 'option_6', false, 'Regional'],
    'multiple select' => ['select', ['option_6', 'option_7'], true, 'Regional, National'],
    'search' => ['search', 'option_6', false, 'Regional'],
    'radio' => ['radio', 'option_6', false, 'Regional'],
    'empty select' => ['select', null, false, '—'],
    'zero label' => ['select', '0', false, '0'],
    'legacy value' => ['select', 'retired-option', false, 'retired-option'],
]);

it('prints select labels in both HTML and generated Word-backed PDF templates', function (mixed $value, bool $multiple, string $expected) {
    Storage::fake('public');
    Mail::fake();
    config(['documents.disk' => 'public']);
    $submission = selectDisplaySubmission('select', $value, $multiple);
    $form = $submission->form;

    $html = app(PdfTemplateRenderer::class)->render(
        $form->pdf_template['html'],
        $submission->payload,
        $form->fields,
    );
    expect($html)->toContain('Scope: '.$expected)
        ->not->toContain('option_6')
        ->not->toContain('option_7');

    $document = app(DocumentGenerationService::class)->generateFromSubmission(
        $submission, null, (int) recordsUser(2)->getKey(),
    );
    Storage::disk('public')->assertExists($document->pdf_path);
    $process = new Process(['pdftotext', Storage::disk('public')->path($document->pdf_path), '-']);
    $process->mustRun();

    expect($process->getOutput())->toContain('Scope: '.$expected)
        ->not->toContain('option_6')
        ->not->toContain('option_7')
        ->and($submission->fresh()->payload['scope'])->toBe($value);
})->with([
    'single select' => ['option_6', false, 'Regional'],
    'multiple select' => [['option_6', 'option_7'], true, 'Regional, National'],
]);
