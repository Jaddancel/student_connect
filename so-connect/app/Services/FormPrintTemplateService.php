<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Template as FormTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Owns the `.docx` that backs a form's printed template — the document the
 * OnlyOffice editor opens in the form builder's "Printed template" step.
 *
 * A form that has never been opened in the new editor is migrated lazily: its
 * legacy `pdf_template.html` (rich text with `data-field` token spans) is
 * converted to Word on first open, so existing forms carry their layout across
 * instead of starting blank. Forms with no legacy template get a stub document.
 */
class FormPrintTemplateService
{
    public function __construct(private readonly DocxTemplateService $docx) {}

    /**
     * The form's active printed template, created on first use.
     */
    public function resolve(Form $form, ?int $userId = null): FormTemplate
    {
        $existing = FormTemplate::query()
            ->where('form_id', $form->getKey())
            ->where('is_active', true)
            ->latest('version')
            ->first();

        if ($existing !== null && $this->fileExists($existing)) {
            return $existing;
        }

        return $this->seed($form, $userId);
    }

    /**
     * Persist a new revision of the template's document.
     *
     * The file is overwritten in place and `version` bumped: OnlyOffice
     * autosaves, so a row per save would pile up fast. Bumping the version and
     * touching the row is what invalidates the editor's document key.
     */
    public function storeRevision(FormTemplate $template, string $contents): FormTemplate
    {
        if ($contents === '') {
            throw new RuntimeException('Refusing to save an empty printed template.');
        }

        Storage::disk($this->disk())->put((string) $template->docx_path, $contents);

        $template->forceFill([
            'version' => (int) ($template->version ?? 1) + 1,
            'is_active' => true,
        ])->save();

        // `updated_at` feeds the document key; make sure it actually moved even
        // when two saves land inside the same second.
        $template->touch();

        return $template->refresh();
    }

    /**
     * Absolute path of the template's document on disk.
     */
    public function absolutePath(FormTemplate $template): string
    {
        return Storage::disk($this->disk())->path((string) $template->docx_path);
    }

    public function fileExists(FormTemplate $template): bool
    {
        $path = (string) ($template->docx_path ?? '');

        return $path !== '' && Storage::disk($this->disk())->exists($path);
    }

    /**
     * Create the form's first .docx, migrating the legacy HTML template if the
     * form has one.
     */
    private function seed(Form $form, ?int $userId): FormTemplate
    {
        $relativePath = $this->pathFor($form);
        // Parenthesised around the ?? deliberately: a cast binds tighter, so
        // `(string) $arr['html'] ?? ''` still warns on a template-less form.
        $legacyHtml = trim((string) (((array) ($form->pdf_template ?? []))['html'] ?? ''));

        $bytes = $legacyHtml !== ''
            ? $this->convertLegacyHtml($legacyHtml)
            : $this->blankDocument($form);

        Storage::disk($this->disk())->put($relativePath, $bytes);

        // Any stale rows for this form step aside so `resolve()` stays single-valued.
        FormTemplate::query()->where('form_id', $form->getKey())->update(['is_active' => false]);

        return FormTemplate::query()->create([
            'form_id' => $form->getKey(),
            'organization_id' => $form->organization_id,
            'uploaded_by' => $userId,
            'template_name' => trim((string) ($form->name ?? 'Printed template')),
            'docx_path' => $relativePath,
            'version' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Convert the legacy rich-text template to Word. Token spans become
     * `{{field_key}}` text on the way through, which is exactly the syntax
     * {@see DocxTemplateService::populate()} fills in later.
     */
    private function convertLegacyHtml(string $html): string
    {
        $generatedPath = $this->docx->htmlToDocx($html);

        try {
            return File::get($generatedPath);
        } finally {
            File::deleteDirectory(dirname($generatedPath));
        }
    }

    /**
     * A minimal starting document for a form with no legacy template.
     */
    private function blankDocument(Form $form): string
    {
        $workDir = storage_path('app/tmp/seed-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($workDir);
        $path = $workDir.'/blank.docx';

        try {
            $phpWord = new \PhpOffice\PhpWord\PhpWord;
            $section = $phpWord->addSection();
            $section->addText(trim((string) ($form->name ?? 'Printed template')), ['bold' => true, 'size' => 16]);
            $section->addTextBreak();
            $section->addText('Use the Field tokens panel to insert form fields, e.g. {{full_name}}.');

            \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($path);

            return File::get($path);
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function pathFor(Form $form): string
    {
        $directory = trim((string) config('documents.templates_directory', 'form-templates'), '/');

        return $directory.'/'.$form->getKey().'/printed-template.docx';
    }

    private function disk(): string
    {
        return (string) config('documents.disk', 'public');
    }
}
