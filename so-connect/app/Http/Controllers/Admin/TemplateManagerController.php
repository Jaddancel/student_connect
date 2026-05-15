<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FormTemplateHelper;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Template;
use App\Models\Template\TemplateDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateManagerController extends Controller
{
    public function index()
    {
        $forms = Form::whereNotNull('route_name')->get();

        $activeTemplates = Template::whereIn('form_id', $forms->pluck('id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('form_id');

        return view('pages.admin.templates.index', compact('forms', 'activeTemplates'));
    }

    public function showUpload(Form $form)
    {
        abort_if($form->route_name === null, 404);

        return view('pages.admin.templates.upload', compact('form'));
    }

    public function storeUpload(Request $request, Form $form)
    {
        abort_if($form->route_name === null, 404);

        $request->validate([
            'template_name' => ['required', 'string', 'max:255'],
            'docx_file' => ['required', 'file', 'mimes:docx', 'max:10240'],
        ]);

        $disk = config('documents.disk', 'public');
        $dir = config('documents.templates_directory', 'form-templates');
        $filename = Str::uuid() . '.docx';

        $relativePath = $request->file('docx_file')
            ->storeAs($dir . '/' . $form->id, $filename, ['disk' => $disk]);

        $version = (Template::where('form_id', $form->id)->max('version') ?? 0) + 1;

        $template = Template::create([
            'form_id' => $form->id,
            'uploaded_by' => $request->user()->getKey(),
            'template_name' => $request->input('template_name'),
            'docx_path' => $relativePath,
            'version' => $version,
            'is_active' => false,
        ]);

        return redirect()->route('admin.templates.verify', $template);
    }

    public function showVerify(Template $template)
    {
        $template->load('form.fields');
        $form = $template->form;

        abort_if($form === null || $form->route_name === null, 404);

        $disk = config('documents.disk', 'public');
        $absolutePath = Storage::disk($disk)->path($template->docx_path);

        try {
            $placeholders = FormTemplateHelper::extractPlaceholdersFromDocx($absolutePath);
        } catch (\Throwable $e) {
            return back()->withErrors(['docx_file' => 'Could not read the uploaded DOCX file. Please try uploading again.']);
        }

        [$matched, $missing, $extra] = $this->compareFieldsToPlaceholders($form->fields, $placeholders);

        return view('pages.admin.templates.verify', compact('template', 'form', 'matched', 'missing', 'extra'));
    }

    public function confirm(Template $template)
    {
        $template->load('form.fields');
        $form = $template->form;

        abort_if($form === null || $form->route_name === null, 404);

        $disk = config('documents.disk', 'public');
        $absolutePath = Storage::disk($disk)->path($template->docx_path);

        try {
            $placeholders = FormTemplateHelper::extractPlaceholdersFromDocx($absolutePath);
        } catch (\Throwable $e) {
            return redirect()->route('admin.templates.index')
                ->withErrors(['template' => 'Could not read the DOCX file. The template was not activated.']);
        }

        $fieldMap = $form->fields->keyBy(
            fn ($f) => FormTemplateHelper::normalizeFieldKey($f->field_key)
        );

        DB::transaction(function () use ($template, $form, $placeholders, $fieldMap) {
            TemplateDescription::where('template_id', $template->id)->delete();

            foreach ($placeholders as $placeholder) {
                $base = rtrim($placeholder, '#');
                $field = $fieldMap[$base] ?? null;

                TemplateDescription::create([
                    'template_id' => $template->id,
                    'form_description_id' => $field?->id,
                    'placeholder_key' => $placeholder,
                    'field_key' => $base,
                    'is_required' => $field?->is_required ?? false,
                ]);
            }

            Template::where('form_id', $template->form_id)
                ->where('id', '!=', $template->id)
                ->update(['is_active' => false]);

            $template->update(['is_active' => true]);
            $form->update(['is_published' => true]);
        });

        return redirect()->route('admin.templates.index')
            ->with('success', "Template \"{$template->template_name}\" activated for {$form->name}.");
    }

    public function destroy(Template $template)
    {
        $formId = $template->form_id;
        $disk = config('documents.disk', 'public');

        TemplateDescription::where('template_id', $template->id)->delete();
        Storage::disk($disk)->delete($template->docx_path);
        $template->delete();

        $stillHasActive = Template::where('form_id', $formId)->where('is_active', true)->exists();

        if (! $stillHasActive) {
            Form::where('id', $formId)->update(['is_published' => false]);
        }

        return redirect()->route('admin.templates.index')
            ->with('success', 'Template removed.');
    }

    private function compareFieldsToPlaceholders($fields, array $placeholders): array
    {
        $normalizedPlaceholders = collect($placeholders)
            ->mapWithKeys(fn ($p) => [rtrim($p, '#') => $p])
            ->all();

        $matched = [];
        $missing = collect();

        foreach ($fields as $field) {
            $key = FormTemplateHelper::normalizeFieldKey($field->field_key);
            if (isset($normalizedPlaceholders[$key])) {
                $matched[$key] = ['field' => $field, 'placeholder' => $normalizedPlaceholders[$key]];
            } else {
                $missing[$key] = $field;
            }
        }

        $fieldKeys = $fields->map(fn ($f) => FormTemplateHelper::normalizeFieldKey($f->field_key))->all();
        $extra = collect($placeholders)
            ->filter(fn ($p) => ! in_array(rtrim($p, '#'), $fieldKeys, true))
            ->values()
            ->all();

        return [$matched, $missing, $extra];
    }
}
