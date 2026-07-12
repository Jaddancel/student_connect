<?php

namespace App\Http\Controllers\Admin;

use App\Forms\FieldType;
use App\Forms\SystemFunction;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Support\UniversalField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin WYSIWYG form builder. Persists a form's canvas arrangement to
 * `forms.layout` and upserts its `form_descriptions` rows from the posted JSON,
 * replacing the DOCX Template Manager.
 */
class FormBuilderController extends Controller
{
    public function index()
    {
        $forms = Form::query()
            ->withCount('fields')
            ->orderBy('name')
            ->get();

        return view('pages.admin.form-builder.index', [
            'title' => 'Form Builder',
            'forms' => $forms,
        ]);
    }

    public function create()
    {
        return view('pages.admin.form-builder.editor', [
            'title' => 'New Form',
            'form' => null,
            'editorData' => $this->blankEditorData(),
            'fieldCatalog' => FieldType::catalog(),
        ]);
    }

    public function edit(Form $form)
    {
        return view('pages.admin.form-builder.editor', [
            'title' => 'Edit Form',
            'form' => $form,
            'editorData' => $this->editorDataFromForm($form),
            'fieldCatalog' => FieldType::catalog(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, null);

        $form = DB::transaction(function () use ($data, $request) {
            $form = Form::create([
                'name' => $data['name'],
                'description_text' => $data['description_text'],
                'route_name' => $data['route_name'],
                'sidebar_group' => $data['sidebar_group'],
                'system_function' => $data['system_function'],
                'is_active' => $data['is_active'],
                'is_published' => $data['is_published'],
                'created_by' => $request->user()?->getKey(),
                'layout' => ['rows' => $data['rows']],
                'pdf_template' => $data['pdf_template'],
            ]);

            $this->syncFields($form, $data['fields']);

            return $form;
        });

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_FORM_BUILDER,
            'created',
            'Created form "'.$form->name.'"',
            ['form_id' => (int) $form->getKey(), 'route_name' => $form->route_name, 'system_function' => $form->system_function],
            $form,
        );

        return response()->json([
            'message' => 'Form created.',
            'redirect' => route('admin.form-builder.edit', $form),
        ]);
    }

    public function update(Request $request, Form $form)
    {
        $data = $this->validatePayload($request, $form);

        DB::transaction(function () use ($data, $form) {
            $form->update([
                'name' => $data['name'],
                'description_text' => $data['description_text'],
                'route_name' => $data['route_name'],
                'sidebar_group' => $data['sidebar_group'],
                'system_function' => $data['system_function'],
                'is_active' => $data['is_active'],
                'is_published' => $data['is_published'],
                'layout' => ['rows' => $data['rows']],
                'pdf_template' => $data['pdf_template'],
            ]);

            $this->syncFields($form, $data['fields']);
        });

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_FORM_BUILDER,
            'updated',
            'Updated form "'.$form->name.'"',
            ['form_id' => (int) $form->getKey(), 'route_name' => $form->route_name, 'system_function' => $form->system_function],
            $form,
        );

        return response()->json([
            'message' => 'Form saved.',
            'redirect' => route('admin.form-builder.edit', $form),
        ]);
    }

    public function destroy(Form $form)
    {
        $form->fields()->delete();
        $form->delete();

        \App\Services\ActionLogger::log(
            \App\Services\ActionLogger::CATEGORY_FORM_BUILDER,
            'deleted',
            'Deleted form "'.$form->name.'"',
            ['form_id' => (int) $form->getKey(), 'route_name' => $form->route_name],
        );

        return redirect()->route('admin.form-builder.index')
            ->with('success', 'Form deleted.');
    }

    /**
     * Upload a header image/logo; returns its disk-relative path for the layout.
     */
    public function uploadAsset(Request $request)
    {
        $request->validate([
            'asset' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('asset')->store(
            'form-headers',
            (string) config('documents.disk', 'public'),
        );

        return response()->json(['path' => $path]);
    }

    /**
     * @return array<string,mixed>
     */
    private function validatePayload(Request $request, ?Form $form): array
    {
        $formId = $form?->getKey();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description_text' => ['nullable', 'string', 'max:5000'],
            'route_name' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('forms', 'route_name')->ignore($formId),
            ],
            'sidebar_group' => ['nullable', 'array'],
            'sidebar_group.*' => ['string', Rule::in(['officer', 'president', 'superadmin', 'admin'])],
            'system_function' => [
                'nullable', 'string', Rule::in(SystemFunction::keys()),
                Rule::unique('forms', 'system_function')->ignore($formId),
            ],
            'is_active' => ['boolean'],
            'is_published' => ['boolean'],
            'fields' => ['present', 'array'],
            'fields.*.field_key' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]+$/'],
            'fields.*.field_label' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => ['required', 'string', Rule::in(FieldType::all())],
            'fields.*.is_required' => ['boolean'],
            'fields.*.placeholder_hint' => ['nullable', 'string', 'max:255'],
            'fields.*.field_options' => ['nullable', 'array'],
            'fields.*.universal_key' => ['nullable', 'string', Rule::in(UniversalField::keys())],
            'rows' => ['present', 'array'],
            'pdf_template' => ['nullable', 'array'],
            'pdf_template.html' => ['nullable', 'string'],
            'pdf_template.page' => ['nullable', 'array'],
            'pdf_template.page.size' => ['nullable', 'string', Rule::in(['a4', 'letter', 'legal'])],
            'pdf_template.page.orientation' => ['nullable', 'string', Rule::in(['portrait', 'landscape'])],
            'pdf_template.font' => ['nullable', 'array'],
            'pdf_template.font.family' => ['nullable', 'string', 'max:120'],
            'pdf_template.font.size' => ['nullable', 'string', 'max:8'],
            'pdf_template.header' => ['nullable', 'array'],
            'pdf_template.footer' => ['nullable', 'array'],
        ], [
            'route_name.regex' => 'The route name may only contain lowercase letters, numbers and hyphens.',
            'fields.*.field_key.regex' => 'Field keys may only contain letters, numbers and underscores.',
        ]);

        // Ensure field keys are unique within the form.
        $keys = array_column($validated['fields'], 'field_key');
        if (count($keys) !== count(array_unique($keys))) {
            abort(response()->json(['message' => 'Field keys must be unique within a form.'], 422));
        }

        $pdfTemplate = $this->cleanPdfTemplate($validated['pdf_template'] ?? []);
        $isPublished = (bool) ($validated['is_published'] ?? false);

        // A form requires a printed PDF template before it can be published.
        if ($isPublished && ! $this->templateHasContent($pdfTemplate['html'])) {
            abort(response()->json([
                'message' => 'A printed PDF template (Step 2) is required before this form can be published.',
                'errors' => ['pdf_template' => ['A printed PDF template is required before publishing.']],
            ], 422));
        }

        return [
            'name' => $validated['name'],
            'description_text' => $validated['description_text'] ?? null,
            'route_name' => $validated['route_name'],
            'sidebar_group' => array_values($validated['sidebar_group'] ?? []),
            'system_function' => ($validated['system_function'] ?? '') !== '' ? $validated['system_function'] : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_published' => $isPublished,
            'fields' => $validated['fields'],
            'rows' => $this->cleanRows($validated['rows'] ?? [], $keys),
            'pdf_template' => $pdfTemplate,
        ];
    }

    /**
     * Normalise + sanitize the printed-PDF template payload.
     *
     * @param  array<string,mixed>  $template
     * @return array{html:string, page:array{size:string, orientation:string}}
     */
    private function cleanPdfTemplate(array $template): array
    {
        $page = (array) ($template['page'] ?? []);
        $size = (string) ($page['size'] ?? 'a4');
        $size = in_array($size, ['a4', 'letter', 'legal'], true) ? $size : 'a4';
        $orientation = (string) ($page['orientation'] ?? 'portrait');
        $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';

        return [
            'html' => $this->sanitizeTemplateHtml((string) ($template['html'] ?? '')),
            'page' => ['size' => $size, 'orientation' => $orientation],
            'font' => $this->cleanFont((array) ($template['font'] ?? [])),
            'header' => $this->cleanHeader((array) ($template['header'] ?? [])),
            'footer' => $this->cleanFooter((array) ($template['footer'] ?? [])),
        ];
    }

    /**
     * The document's default font family + size. Family is whitelisted to the
     * dompdf-safe editor options; size is clamped to a sane px range.
     *
     * @param  array<string,mixed>  $font
     * @return array{family:string, size:string}
     */
    private function cleanFont(array $font): array
    {
        $default = "'Times New Roman', Times, serif";
        $allowed = [
            'Arial, Helvetica, sans-serif',
            "'Times New Roman', Times, serif",
            'Georgia, serif',
            "'Courier New', Courier, monospace",
            "'DejaVu Sans', sans-serif",
        ];
        $family = (string) ($font['family'] ?? $default);
        if (! in_array($family, $allowed, true)) {
            $family = $default;
        }

        $size = 12;
        if (preg_match('/^(\d{1,2})px$/', (string) ($font['size'] ?? ''), $m)) {
            $size = min(48, max(8, (int) $m[1]));
        }

        return ['family' => $family, 'size' => $size.'px'];
    }

    /**
     * Normalise the printed footer (image only, for now).
     *
     * @param  array<string,mixed>  $footer
     * @return array<string,mixed>
     */
    private function cleanFooter(array $footer): array
    {
        return array_filter([
            'image' => $footer['image'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function templateHasContent(string $html): bool
    {
        // Content = any visible text or at least one field token.
        if (str_contains($html, 'data-field=')) {
            return true;
        }

        return trim(strip_tags($html)) !== '';
    }

    /**
     * Authoritatively strip the template HTML down to the editor's allowed tag
     * and attribute set (headings, paragraphs, inline emphasis, lists, field
     * tokens, basic alignment) so only dompdf-safe markup is persisted.
     */
    private function sanitizeTemplateHtml(string $html): string
    {
        return \App\Forms\TemplateHtmlSanitizer::sanitize($html);
    }

    /**
     * Upsert form_descriptions from the posted fields, pruning removed keys.
     *
     * @param  array<int,array<string,mixed>>  $fields
     */
    private function syncFields(Form $form, array $fields): void
    {
        $keys = [];
        foreach ($fields as $order => $field) {
            $keys[] = $field['field_key'];
            FormDescription::updateOrCreate(
                ['form_id' => $form->id, 'field_key' => $field['field_key']],
                [
                    'field_label' => $field['field_label'],
                    'field_type' => $field['field_type'],
                    'is_required' => (bool) ($field['is_required'] ?? false),
                    'field_order' => $order + 1,
                    'placeholder_hint' => $field['placeholder_hint'] ?? null,
                    'field_options' => $field['field_options'] ?? null,
                    'universal_key' => ($field['universal_key'] ?? '') !== '' ? $field['universal_key'] : null,
                ],
            );
        }

        FormDescription::where('form_id', $form->id)
            ->whereNotIn('field_key', $keys)
            ->delete();
    }

    /**
     * @param  array<string,mixed>  $header
     * @return array<string,mixed>
     */
    private function cleanHeader(array $header): array
    {
        $align = $header['align'] ?? 'center';
        $align = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';

        return array_filter([
            'image' => $header['image'] ?? null,
            'logo' => $header['logo'] ?? null,
            'title' => $header['title'] ?? null,
            'subtitle' => $header['subtitle'] ?? null,
            'align' => $align,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Normalise rows, keeping only known field keys and valid spans.
     *
     * @param  array<int,mixed>  $rows
     * @param  array<int,string>  $validKeys
     * @return array<int,array<string,mixed>>
     */
    private function cleanRows(array $rows, array $validKeys): array
    {
        $validKeys = array_flip($validKeys);
        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $columns = [];
            foreach (($row['columns'] ?? []) as $col) {
                if (! is_array($col)) {
                    continue;
                }
                $fieldKeys = array_values(array_filter(
                    array_map('strval', (array) ($col['fields'] ?? [])),
                    fn ($k) => isset($validKeys[$k]),
                ));
                $columns[] = [
                    'span' => max(1, min(12, (int) ($col['span'] ?? 12))),
                    'fields' => $fieldKeys,
                ];
            }
            if (! empty($columns)) {
                $clean[] = ['columns' => $columns];
            }
        }

        return $clean;
    }

    /**
     * @return array<string,mixed>
     */
    private function blankEditorData(): array
    {
        return [
            'name' => '',
            'description_text' => '',
            'route_name' => '',
            'sidebar_group' => ['admin'],
            'system_function' => '',
            'is_active' => true,
            'is_published' => false,
            'fields' => [],
            'rows' => [],
            'pdf_template' => [
                'html' => '',
                'page' => ['size' => 'a4', 'orientation' => 'portrait'],
                'font' => ['family' => "'Times New Roman', Times, serif", 'size' => '12px'],
                'header' => ['align' => 'center'],
                'footer' => [],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function editorDataFromForm(Form $form): array
    {
        $layout = (array) ($form->layout ?? []);

        $fields = $form->fields()->get()->map(fn (FormDescription $f) => [
            'field_key' => $f->field_key,
            'field_label' => $f->field_label,
            'field_type' => $f->field_type,
            'is_required' => (bool) $f->is_required,
            'placeholder_hint' => $f->placeholder_hint,
            'field_options' => (array) ($f->field_options ?? []),
            'universal_key' => $f->universal_key ?? '',
        ])->values()->all();

        $pdfTemplate = (array) ($form->pdf_template ?? []);

        // Migrate a legacy letterhead stored on layout.header into the template
        // header, so forms authored before the move don't lose it.
        $header = (array) ($pdfTemplate['header'] ?? ($layout['header'] ?? ['align' => 'center']));

        return [
            'name' => $form->name,
            'description_text' => $form->description_text,
            'route_name' => $form->route_name,
            'sidebar_group' => (array) ($form->sidebar_group ?? []),
            'system_function' => (string) ($form->system_function ?? ''),
            'is_active' => (bool) $form->is_active,
            'is_published' => (bool) $form->is_published,
            'fields' => $fields,
            'rows' => $layout['rows'] ?? [],
            'pdf_template' => [
                'html' => (string) ($pdfTemplate['html'] ?? ''),
                'page' => [
                    'size' => (string) ($pdfTemplate['page']['size'] ?? 'a4'),
                    'orientation' => (string) ($pdfTemplate['page']['orientation'] ?? 'portrait'),
                ],
                'font' => [
                    'family' => (string) ($pdfTemplate['font']['family'] ?? "'Times New Roman', Times, serif"),
                    'size' => (string) ($pdfTemplate['font']['size'] ?? '12px'),
                ],
                'header' => $header,
                'footer' => (array) ($pdfTemplate['footer'] ?? []),
            ],
        ];
    }

    /**
     * Admin preview of a form (and, optionally, its printed document), bypassing
     * the live page's `is_active`/`is_published` gates. Resolves by form id so
     * drafts without a `route_name` still preview.
     */
    public function preview(Request $request, Form $form)
    {
        // "Preview printed document": render the PDF template with sample/blank
        // values inline, without persisting a submission or generated document.
        if ($request->query('document')) {
            $pdfTemplate = (array) ($form->pdf_template ?? []);
            $templateHtml = (string) ($pdfTemplate['html'] ?? '');

            if (trim($templateHtml) === '') {
                abort(404, 'This form has no printed template yet.');
            }

            $fields = $form->fields()->get();
            $page = (array) ($pdfTemplate['page'] ?? []);
            $size = (string) ($page['size'] ?? 'a4');
            $size = in_array($size, ['a4', 'letter', 'legal'], true) ? $size : 'a4';
            $orientation = (string) ($page['orientation'] ?? 'portrait');
            $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';

            $body = app(\App\Forms\PdfTemplateRenderer::class)->render(
                $templateHtml,
                $this->sampleValues($fields),
                $fields,
                organization: \App\Support\OrganizationField::resolveOrganization($request->user()),
            );

            $html = view('documents.form-template-pdf', [
                'form' => $form,
                'body' => $body,
                'page' => $page,
                'font' => (array) ($pdfTemplate['font'] ?? []),
                'header' => (array) ($pdfTemplate['header'] ?? []),
                'footer' => (array) ($pdfTemplate['footer'] ?? []),
            ])->render();

            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper($size, $orientation)
                ->stream('preview-'.($form->route_name ?: $form->getKey()).'.pdf');
        }

        return view('pages.form.render', [
            'title' => $form->name.' (Preview)',
            'form' => $form,
            'fields' => $form->fields()->get(),
            'preview' => true,
        ]);
    }

    /**
     * Sample values for the printed-document preview: a readable placeholder per
     * fillable field, keyed by field_key.
     *
     * @param  \Illuminate\Support\Collection<int,FormDescription>  $fields
     * @return array<string,mixed>
     */
    private function sampleValues($fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            if (FieldType::isPresentational($field->field_type) || FieldType::isFileLike($field->field_type)
                || $field->field_type === FieldType::SIGNATURE) {
                continue;
            }
            $values[$field->field_key] = '['.$field->field_label.']';
        }

        return $values;
    }

    /**
     * Export the current in-wizard template HTML to a downloadable .docx, with
     * field tokens bridged to `{{field_key}}` text placeholders.
     */
    public function exportDocx(Request $request, \App\Services\DocxTemplateService $docx)
    {
        $validated = $request->validate([
            'html' => ['nullable', 'string'],
        ]);

        $html = $this->sanitizeTemplateHtml((string) ($validated['html'] ?? ''));
        $path = $docx->htmlToDocx($html);

        return response()->download($path, 'form-template.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Import a .docx into the wizard: convert to sanitized HTML and map
     * `{{field_key}}` placeholders back into field-token chips. Operates on
     * in-wizard state (no saved form required).
     */
    public function importDocx(Request $request, \App\Services\DocxTemplateService $docx)
    {
        $request->validate([
            'docx' => ['required', 'file', 'mimes:docx', 'max:10240'],
            'fields' => ['nullable', 'string'],
        ]);

        $fields = collect(json_decode((string) $request->input('fields', '[]'), true) ?: [])
            ->filter(fn ($f) => is_array($f) && isset($f['key']))
            ->mapWithKeys(fn ($f) => [(string) $f['key'] => (string) ($f['label'] ?? $f['key'])])
            ->all();

        try {
            $html = $docx->docxToHtml($request->file('docx'), $fields);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not import this document: '.$e->getMessage()], 422);
        }

        return response()->json(['html' => $this->sanitizeTemplateHtml($html)]);
    }
}
