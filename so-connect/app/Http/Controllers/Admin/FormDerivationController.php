<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FormTemplateHelper;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Form\FormDescription;
use App\Models\FormSubmission;
use App\Services\DocxFormStructureParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload → review → confirm flow for deriving a web form from a blank DOCX.
 * Mirrors {@see TemplateManagerController}'s pattern.
 */
class FormDerivationController extends Controller
{
    private const STORAGE_DIR = 'form-derivation';

    public function showUpload()
    {
        return view('pages.admin.form-derivation.upload');
    }

    public function storeUpload(Request $request)
    {
        $validated = $request->validate([
            'form_name' => ['required', 'string', 'max:255'],
            'docx_file' => ['required', 'file', 'mimes:docx', 'max:10240'],
        ]);

        $disk = config('documents.disk', 'public');
        $filename = Str::uuid().'.docx';

        $relativePath = $request->file('docx_file')
            ->storeAs(self::STORAGE_DIR, $filename, ['disk' => $disk]);

        // Create a draft (inactive) form; fields are attached at confirm time.
        $form = Form::create([
            'name' => $validated['form_name'],
            'created_by' => $request->user()?->getKey(),
            'is_active' => false,
            'is_published' => false,
            'ocr_reference_docx' => $relativePath,
        ]);

        return redirect()->route('admin.form-derivation.review', $form);
    }

    public function showReview(Form $form, DocxFormStructureParser $parser)
    {
        abort_if($form->ocr_reference_docx === null, 404);

        $disk = config('documents.disk', 'public');
        $absolutePath = Storage::disk($disk)->path($form->ocr_reference_docx);

        try {
            $detectedFields = $parser->parse($absolutePath);
        } catch (\Throwable $e) {
            return redirect()->route('admin.form-derivation.upload')
                ->withErrors(['docx_file' => 'Could not read the uploaded DOCX file. Please try again.']);
        }

        // If the form already has saved fields (re-review), prefer those.
        $existing = $form->fields()->get();
        if ($existing->isNotEmpty()) {
            $detectedFields = $existing->map(fn (FormDescription $f) => [
                'field_label' => $f->field_label,
                'field_key' => $f->field_key,
                'field_type' => $f->field_type,
                'is_required' => (bool) $f->is_required,
                'field_order' => (int) $f->field_order,
                'field_options' => $f->field_options,
            ])->all();
        }

        $fieldTypes = ['text', 'textarea', 'row', 'file', 'checkbox', 'date', 'email', 'number'];

        return view('pages.admin.form-derivation.review', compact('form', 'detectedFields', 'fieldTypes'));
    }

    public function confirm(Request $request, Form $form)
    {
        abort_if($form->ocr_reference_docx === null, 404);

        $validated = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.field_label' => ['required', 'string', 'max:255'],
            'fields.*.field_key' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => ['required', 'string', 'in:text,textarea,row,file,checkbox,date,email,number'],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.field_order' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($form, $validated) {
            FormDescription::where('form_id', $form->id)->delete();

            $usedKeys = [];
            foreach (array_values($validated['fields']) as $i => $field) {
                $key = $this->uniqueKey($field['field_key'] ?: $field['field_label'], $usedKeys);

                FormDescription::create([
                    'form_id' => $form->id,
                    'field_key' => $key,
                    'field_label' => $field['field_label'],
                    'field_type' => $field['field_type'],
                    'is_required' => (bool) ($field['is_required'] ?? false),
                    'field_order' => (int) ($field['field_order'] ?? $i + 1),
                ]);
            }

            $form->update(['is_active' => true]);
        });

        return redirect()->route('admin.form-derivation.review', $form)
            ->with('success', "Form \"{$form->name}\" saved with ".count($validated['fields']).' field(s).');
    }

    public function destroy(Form $form)
    {
        abort_if($form->ocr_reference_docx === null, 404);

        // Guard: never discard a form that already has submissions.
        abort_if(FormSubmission::where('form_id', $form->id)->exists(), 403);

        $disk = config('documents.disk', 'public');

        DB::transaction(function () use ($form, $disk) {
            FormDescription::where('form_id', $form->id)->delete();
            if ($form->ocr_reference_docx) {
                Storage::disk($disk)->delete($form->ocr_reference_docx);
            }
            $form->delete();
        });

        return redirect()->route('admin.form-derivation.upload')
            ->with('success', 'Draft form discarded.');
    }

    /**
     * @param  array<string, bool>  $usedKeys
     */
    private function uniqueKey(string $candidate, array &$usedKeys): string
    {
        $base = FormTemplateHelper::normalizeFieldKey($candidate);
        $key = $base;
        $i = 2;

        while (isset($usedKeys[$key])) {
            $key = $base.'_'.$i;
            $i++;
        }

        $usedKeys[$key] = true;

        return $key;
    }
}
