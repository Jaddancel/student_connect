<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Services\FormPrintTemplateService;
use Illuminate\Http\Request;

/**
 * Directory of published builder forms, searchable by name and purpose
 * (`description_text`). Complements the global top-bar search with a dedicated
 * browsable page. The literal `/forms` route is registered before the generic
 * `/forms/{routeName}` renderer so it always wins.
 *
 * Each card opens a blank printable PDF generated from one active
 * Step 2 template ({@see FormBlankPdfController}), so each usable stored
 * template gets its own card. Available to organization
 * officers/presidents (user type 3) and to admins (user type 2); everyone else
 * gets an empty directory. Every published, active form is eligible — the
 * system-function forms (sign-up, new-event, etc.) included — since the card
 * only offers a blank printable PDF and never the online renderer.
 */
class FormDirectoryController extends Controller
{
    public function index(Request $request, FormPrintTemplateService $templates)
    {
        $forms = collect();

        if (Form::canViewBlankPdf($request->user())) {
            $forms = Form::query()
                ->whereNotNull('route_name')
                ->where('is_published', true)
                ->where('is_active', true)
                ->directoryEligible()
                ->whereHas('templates', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'name', 'route_name', 'description_text'])
                // A template row whose .docx vanished from storage can't print —
                // keep the directory in step with what the endpoint will serve.
                ->filter(fn (Form $form) => $templates->activeTemplates($form)->isNotEmpty())
                ->values();
        }

        return view('pages.form.directory', [
            'title' => 'Forms',
            'forms' => $forms->flatMap(fn (Form $form) => $templates->activeTemplates($form)->map(fn ($template) => [
                'name' => $form->name,
                'purpose' => (string) ($form->description_text ?? ''),
                'template_name' => (string) ($template->template_name ?: $form->name),
                'url' => route('forms.blank-pdf', ['routeName' => $form->route_name, 'template' => $template->getKey()]),
            ]))->values(),
        ]);
    }
}
