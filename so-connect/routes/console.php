<?php

use App\Helpers\FormTemplateHelper;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\Template;
use App\Models\Template\TemplateDescription;
use App\Services\DocumentGenerationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('forms:bootstrap-template
    {name : Display name of the form}
    {docx_path : Path to source DOCX template}
    {--organization_id= : Target organization id}
    {--user_id= : Template uploader user id}
    {--description= : Optional form description}
    {--publish : Mark the new form as published}', function (DocumentGenerationService $generationService): int {
    $name = trim((string) $this->argument('name'));
    $docxPath = trim((string) $this->argument('docx_path'));
    $organizationId = max((int) $this->option('organization_id'), 0);
    $uploaderUserId = max((int) $this->option('user_id'), 0);
    $description = trim((string) $this->option('description'));
    $publish = (bool) $this->option('publish');

    if ($name === '') {
        $this->error('Form name is required.');

        return self::FAILURE;
    }

    $absolutePath = $docxPath;

    if (! str_starts_with($absolutePath, '/')) {
        $absolutePath = base_path($docxPath);
    }

    if (! is_file($absolutePath)) {
        $this->error('DOCX file was not found at: '.$absolutePath);

        return self::FAILURE;
    }

    $placeholders = FormTemplateHelper::extractPlaceholdersFromDocx($absolutePath);

    if (empty($placeholders)) {
        $this->error('No {{field_name}} placeholders were found in this DOCX file.');

        return self::FAILURE;
    }

    $disk = (string) config('documents.disk', 'public');
    $templatesDirectory = trim((string) config('documents.templates_directory', 'form-templates'), '/');
    $targetRelativePath = $templatesDirectory.'/'.now()->format('Y/m/d').'/'.Str::slug($name).'-'.Str::lower(Str::random(8)).'.docx';

    $sourceContents = file_get_contents($absolutePath);

    if (! is_string($sourceContents) || $sourceContents === '') {
        $this->error('Unable to read DOCX source contents.');

        return self::FAILURE;
    }

    Storage::disk($disk)->put($targetRelativePath, $sourceContents);

    $createdForm = null;
    $createdTemplate = null;

    try {
        DB::transaction(function () use (
            &$createdForm,
            &$createdTemplate,
            $name,
            $description,
            $organizationId,
            $uploaderUserId,
            $publish,
            $targetRelativePath,
            $placeholders,
            $generationService,
        ) {
            $createdForm = Form::query()->create([
                'name' => $name,
                'description_text' => $description !== '' ? $description : null,
                'organization_id' => $organizationId > 0 ? $organizationId : null,
                'created_by' => $uploaderUserId > 0 ? $uploaderUserId : null,
                'is_active' => true,
                'is_published' => $publish,
            ]);

            $createdTemplate = Template::query()->create([
                'form_id' => (int) $createdForm->getKey(),
                'organization_id' => $organizationId > 0 ? $organizationId : null,
                'uploaded_by' => $uploaderUserId > 0 ? $uploaderUserId : null,
                'template_name' => $name.' Template',
                'docx_path' => $targetRelativePath,
                'version' => 1,
                'is_active' => true,
            ]);

            foreach ($placeholders as $index => $placeholder) {
                $fieldKey = FormTemplateHelper::normalizeFieldKey($placeholder);
                $fieldLabel = (string) Str::of($fieldKey)->replace('_', ' ')->title();

                $field = FormDescription::query()->create([
                    'form_id' => (int) $createdForm->getKey(),
                    'field_key' => $fieldKey,
                    'field_label' => $fieldLabel,
                    'field_type' => 'text',
                    'is_required' => true,
                    'field_order' => $index,
                    'placeholder_hint' => FormTemplateHelper::wrapPlaceholder($fieldKey),
                    'field_options' => null,
                ]);

                TemplateDescription::query()->create([
                    'template_id' => (int) $createdTemplate->getKey(),
                    'form_description_id' => (int) $field->getKey(),
                    'placeholder_key' => $fieldKey,
                    'field_key' => $fieldKey,
                    'is_required' => true,
                ]);
            }

            if ($organizationId > 0 && $uploaderUserId > 0) {
                $generationService->createFormUploadRequest(
                    $organizationId,
                    (int) $createdForm->getKey(),
                    (int) $createdTemplate->getKey(),
                    $uploaderUserId,
                );
            }
        });
    } catch (\Throwable $throwable) {
        Storage::disk($disk)->delete($targetRelativePath);

        $this->error('Failed to bootstrap template: '.$throwable->getMessage());

        return self::FAILURE;
    }

    $this->info('Form template bootstrap complete.');
    $this->line('Form ID: '.(int) $createdForm?->getKey());
    $this->line('Template ID: '.(int) $createdTemplate?->getKey());
    $this->line('Template path: '.$targetRelativePath);
    $this->line('Extracted placeholders: '.implode(', ', $placeholders));

    return self::SUCCESS;
})->purpose('Create a mapped form + template from a DOCX file with {{field_name}} placeholders');
