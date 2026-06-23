<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FormTemplateHelper;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessFormWizardAiReview;
use App\Models\Form;
use App\Models\Form\FormDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormCreationWizardController extends Controller
{
    private const SESSION_FORM_ID = 'wizard_draft_form_id';
    private const CACHE_PREFIX = 'wizard_ai_result_';
    private const CACHE_TTL = 3600;

    public function showStart()
    {
        return view('pages.admin.form-wizard.step1-upload');
    }

    public function storeUpload(Request $request)
    {
        $request->validate([
            'docx_file' => ['required', 'file', 'mimes:docx', 'max:10240'],
        ]);

        $disk = config('documents.disk', 'public');
        $filename = Str::uuid().'.docx';
        $path = $request->file('docx_file')->storeAs('form-derivation', $filename, ['disk' => $disk]);

        $form = Form::create([
            'name' => 'New Form (Draft)',
            'is_active' => false,
            'is_published' => false,
            'ocr_reference_docx' => $path,
        ]);

        $request->session()->put(self::SESSION_FORM_ID, $form->id);

        return redirect()->route('admin.form-wizard.meta');
    }

    public function showMeta(Request $request)
    {
        $form = $this->getDraftOrAbort($request);

        return view('pages.admin.form-wizard.step2-meta', compact('form'));
    }

    public function saveMeta(Request $request)
    {
        $form = $this->getDraftOrAbort($request);

        $request->validate([
            'form_title' => ['required', 'string', 'max:255'],
            'form_purpose' => ['nullable', 'string', 'max:1000'],
        ]);

        $form->update([
            'name' => $request->input('form_title'),
            'description_text' => $request->input('form_purpose'),
        ]);

        // Clear stale AI result so a new job is dispatched on the AI review step
        Cache::forget(self::CACHE_PREFIX.$form->id);

        return redirect()->route('admin.form-wizard.ai-review');
    }

    public function showAiReview(Request $request)
    {
        $form = $this->getDraftOrAbort($request);
        $cacheKey = self::CACHE_PREFIX.$form->id;

        $cached = Cache::get($cacheKey);

        if (! $cached) {
            Cache::put($cacheKey, ['status' => 'pending'], self::CACHE_TTL);
            ProcessFormWizardAiReview::dispatch($form->id);
        }

        return view('pages.admin.form-wizard.step3-ai-review', [
            'form' => $form,
            'aiResult' => $cached,
        ]);
    }

    public function aiStatus(Request $request)
    {
        $formId = $request->session()->get(self::SESSION_FORM_ID);
        if (! $formId) {
            return response()->json(['status' => 'error'], 404);
        }

        $result = Cache::get(self::CACHE_PREFIX.$formId, ['status' => 'pending']);

        return response()->json($result);
    }

    public function confirmAiReview(Request $request)
    {
        $form = $this->getDraftOrAbort($request);
        $choice = $request->input('choice'); // 'looks_good' | 'revise'

        if ($choice === 'looks_good') {
            $cached = Cache::get(self::CACHE_PREFIX.$form->id, []);
            $this->upsertFormDescriptions($form, $cached['fields'] ?? []);

            return redirect()->route('admin.form-wizard.final');
        }

        return redirect()->route('admin.form-wizard.revise');
    }

    public function showRevise(Request $request)
    {
        $form = $this->getDraftOrAbort($request);
        $form->load('fields');

        $aiFields = [];
        if ($form->fields->isEmpty()) {
            $cached = Cache::get(self::CACHE_PREFIX.$form->id, []);
            $aiFields = $cached['fields'] ?? [];
        }

        return view('pages.admin.form-wizard.step4-revise', compact('form', 'aiFields'));
    }

    public function saveRevise(Request $request)
    {
        $form = $this->getDraftOrAbort($request);

        $request->validate([
            'fields' => ['nullable', 'array'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.field_key' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => ['required', 'in:text,textarea,checkbox,date,number,email'],
            'fields.*.is_required' => ['nullable'],
            'fields.*.field_order' => ['nullable', 'integer'],
        ]);

        $this->upsertFormDescriptions($form, $request->input('fields', []));

        return redirect()->route('admin.form-wizard.final');
    }

    public function showFinal(Request $request)
    {
        $form = $this->getDraftOrAbort($request);
        $form->load('fields');

        return view('pages.admin.form-wizard.step5-final', compact('form'));
    }

    public function confirm(Request $request)
    {
        $form = $this->getDraftOrAbort($request);
        $form->update(['is_active' => true]);

        Cache::forget(self::CACHE_PREFIX.$form->id);
        $request->session()->forget(self::SESSION_FORM_ID);

        return redirect()->route('admin.templates.index')
            ->with('success', "Form \"{$form->name}\" created and activated successfully.");
    }

    public function discard(Request $request)
    {
        $formId = $request->session()->pull(self::SESSION_FORM_ID);

        if ($formId) {
            $form = Form::find($formId);
            if ($form && $form->submissions()->doesntExist()) {
                if ($form->ocr_reference_docx) {
                    Storage::disk(config('documents.disk', 'public'))->delete($form->ocr_reference_docx);
                }
                FormDescription::where('form_id', $form->id)->delete();
                $form->delete();
            }
            Cache::forget(self::CACHE_PREFIX.$formId);
        }

        return redirect()->route('admin.templates.index')
            ->with('success', 'Draft form discarded.');
    }

    private function getDraftOrAbort(Request $request): Form
    {
        $formId = $request->session()->get(self::SESSION_FORM_ID);
        abort_if(! $formId, 404, 'No wizard in progress.');

        $form = Form::find($formId);
        abort_if(! $form, 404, 'Draft form not found.');

        return $form;
    }

    private function upsertFormDescriptions(Form $form, array $fields): void
    {
        FormDescription::where('form_id', $form->id)->delete();

        foreach ($fields as $i => $field) {
            $rawKey = $field['field_key'] ?? $field['label'] ?? 'field';
            $fieldKey = FormTemplateHelper::normalizeFieldKey($rawKey);

            FormDescription::create([
                'form_id' => $form->id,
                'field_label' => $field['label'] ?? $field['field_label'] ?? '',
                'field_key' => $fieldKey,
                'field_type' => $field['field_type'] ?? 'text',
                'is_required' => (bool) ($field['is_required'] ?? false),
                'field_order' => (int) ($field['field_order'] ?? $i + 1),
            ]);
        }
    }
}
