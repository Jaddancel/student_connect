<?php

namespace App\Http\Controllers;

use App\Forms\FieldType;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generic renderer for WYSIWYG builder forms. One controller serves every form
 * defined through the builder, replacing bespoke per-form pages.
 *
 * Submissions are stored with a payload keyed by `field_key` — the compatibility
 * contract that keeps the admin approval pages working.
 */
class FormRenderController extends Controller
{
    public function show(string $routeName)
    {
        $form = Form::query()
            ->where('route_name', $routeName)
            ->where('is_active', true)
            ->firstOrFail();

        $fields = $form->fields()->get();

        return view('pages.form.render', [
            'title' => $form->name,
            'form' => $form,
            'fields' => $fields,
        ]);
    }

    public function submit(Request $request, string $routeName, DocumentGenerationService $docService)
    {
        $form = Form::query()
            ->where('route_name', $routeName)
            ->where('is_active', true)
            ->firstOrFail();

        // Defense in depth: an untemplated form must never accept a submission,
        // since it could not produce its printed document.
        $pdfTemplate = (array) ($form->pdf_template ?? []);
        if (trim((string) ($pdfTemplate['html'] ?? '')) === '') {
            abort(422, 'This form is not ready to accept submissions yet (no printed template).');
        }

        $fields = $form->fields()->get();

        // Build validation rules dynamically from the field catalog.
        $rules = [];
        foreach ($fields as $field) {
            if (FieldType::isPresentational($field->field_type)) {
                continue;
            }
            $fieldRules = FieldType::validationRules(
                $field->field_type,
                (bool) $field->is_required,
                (array) ($field->field_options ?? []),
            );
            if (! empty($fieldRules)) {
                $rules[$field->field_key] = $fieldRules;
            }
        }

        $validated = $request->validate($rules);

        $payload = [];
        foreach ($fields as $field) {
            $key = $field->field_key;
            $type = $field->field_type;

            if (FieldType::isPresentational($type)) {
                continue;
            }

            if (FieldType::isFileLike($type)) {
                if ($request->hasFile($key)) {
                    $payload[$key] = $request->file($key)->store(
                        'form-uploads/'.$form->route_name,
                        (string) config('documents.disk', 'public'),
                    );
                } else {
                    $payload[$key] = null;
                }
                continue;
            }

            if ($type === FieldType::SIGNATURE) {
                $payload[$key] = $this->storeSignature($validated[$key] ?? null, $form->route_name);
                continue;
            }

            $payload[$key] = $validated[$key] ?? null;
        }

        $user = $request->user();
        $submission = FormSubmission::query()->create([
            'form_id' => (int) $form->getKey(),
            'organization_id' => $form->organization_id,
            'submitted_by' => $user ? (int) $user->getKey() : null,
            'payload' => $payload,
            'submitted_at' => now(),
        ]);

        try {
            $docService->generateFromSubmission(
                $submission->fresh('form'),
                null,
                $user ? (int) $user->getKey() : 0,
            );

            return redirect()->route('documents.index')
                ->with('success', $form->name.' submitted and the PDF has been generated.');
        } catch (\Throwable $e) {
            return redirect()->route('forms.render', $form->route_name)
                ->with('success', $form->name.' submitted, but PDF generation failed: '.$e->getMessage());
        }
    }

    /**
     * Persist a captured signature (a base64 PNG data-URL) as a file and return
     * its disk-relative path, or null if nothing was drawn.
     */
    private function storeSignature(?string $dataUrl, string $routeName): ?string
    {
        if (! is_string($dataUrl) || ! str_starts_with($dataUrl, 'data:image')) {
            return null;
        }

        $parts = explode(',', $dataUrl, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $binary = base64_decode($parts[1], true);
        if ($binary === false) {
            return null;
        }

        $path = 'form-uploads/'.$routeName.'/signatures/'.Str::random(20).'.png';
        Storage::disk((string) config('documents.disk', 'public'))->put($path, $binary);

        return $path;
    }
}
