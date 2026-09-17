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
 * Each card opens a blank printable PDF generated from the form's active
 * Step 2 template ({@see FormBlankPdfController}), so only forms backed by a
 * usable stored template are listed. Available to organization
 * officers/presidents (user type 3) and to admins (user type 2); everyone else
 * gets an empty directory. Forms bound to the sign-up / new-event /
 * new-workplan / new-organization system functions are reached through their
 * own dedicated flows, so they are not listed here.
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
                ->filter(fn (Form $form) => $templates->activeStored($form) !== null)
                ->values();
        }

        return view('pages.form.directory', [
            'title' => 'Forms',
            'forms' => $forms->map(fn (Form $form) => [
                'name' => $form->name,
                'purpose' => (string) ($form->description_text ?? ''),
                'url' => route('forms.blank-pdf', $form->route_name),
            ])->values(),
        ]);
    }
}
