<?php

namespace App\Http\Controllers\Admin;

use App\Forms\FieldType;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Form\FormDescription;
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
                'route_name' => $data['route_name'],
                'sidebar_group' => $data['sidebar_group'],
                'is_active' => $data['is_active'],
                'is_published' => $data['is_published'],
                'created_by' => $request->user()?->getKey(),
                'layout' => ['header' => $data['header'], 'rows' => $data['rows']],
            ]);

            $this->syncFields($form, $data['fields']);

            return $form;
        });

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
                'route_name' => $data['route_name'],
                'sidebar_group' => $data['sidebar_group'],
                'is_active' => $data['is_active'],
                'is_published' => $data['is_published'],
                'layout' => ['header' => $data['header'], 'rows' => $data['rows']],
            ]);

            $this->syncFields($form, $data['fields']);
        });

        return response()->json([
            'message' => 'Form saved.',
            'redirect' => route('admin.form-builder.edit', $form),
        ]);
    }

    public function destroy(Form $form)
    {
        $form->fields()->delete();
        $form->delete();

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
            'route_name' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('forms', 'route_name')->ignore($formId),
            ],
            'sidebar_group' => ['nullable', 'array'],
            'sidebar_group.*' => ['string', Rule::in(['officer', 'president', 'superadmin', 'admin'])],
            'is_active' => ['boolean'],
            'is_published' => ['boolean'],
            'header' => ['nullable', 'array'],
            'fields' => ['present', 'array'],
            'fields.*.field_key' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_]+$/'],
            'fields.*.field_label' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => ['required', 'string', Rule::in(FieldType::all())],
            'fields.*.is_required' => ['boolean'],
            'fields.*.placeholder_hint' => ['nullable', 'string', 'max:255'],
            'fields.*.field_options' => ['nullable', 'array'],
            'rows' => ['present', 'array'],
        ], [
            'route_name.regex' => 'The route name may only contain lowercase letters, numbers and hyphens.',
            'fields.*.field_key.regex' => 'Field keys may only contain letters, numbers and underscores.',
        ]);

        // Ensure field keys are unique within the form.
        $keys = array_column($validated['fields'], 'field_key');
        if (count($keys) !== count(array_unique($keys))) {
            abort(response()->json(['message' => 'Field keys must be unique within a form.'], 422));
        }

        return [
            'name' => $validated['name'],
            'route_name' => $validated['route_name'],
            'sidebar_group' => array_values($validated['sidebar_group'] ?? []),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'header' => $this->cleanHeader($validated['header'] ?? []),
            'fields' => $validated['fields'],
            'rows' => $this->cleanRows($validated['rows'] ?? [], $keys),
        ];
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
        return array_filter([
            'image' => $header['image'] ?? null,
            'logo' => $header['logo'] ?? null,
            'title' => $header['title'] ?? null,
            'subtitle' => $header['subtitle'] ?? null,
            'align' => in_array(($header['align'] ?? 'center'), ['left', 'center', 'right'], true)
                ? $header['align'] : 'center',
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
            'route_name' => '',
            'sidebar_group' => ['admin'],
            'is_active' => true,
            'is_published' => false,
            'header' => ['align' => 'center'],
            'fields' => [],
            'rows' => [],
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
        ])->values()->all();

        return [
            'name' => $form->name,
            'route_name' => $form->route_name,
            'sidebar_group' => (array) ($form->sidebar_group ?? []),
            'is_active' => (bool) $form->is_active,
            'is_published' => (bool) $form->is_published,
            'header' => (array) ($layout['header'] ?? ['align' => 'center']),
            'fields' => $fields,
            'rows' => $layout['rows'] ?? [],
        ];
    }
}
