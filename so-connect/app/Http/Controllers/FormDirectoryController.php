<?php

namespace App\Http\Controllers;

use App\Forms\SystemFunction;
use App\Models\Form;
use Illuminate\Http\Request;

/**
 * Directory of published builder forms, searchable by name and purpose
 * (`description_text`). Complements the global top-bar search with a dedicated
 * browsable page. The literal `/forms` route is registered before the generic
 * `/forms/{routeName}` renderer so it always wins.
 *
 * Built forms are available to organization officers/presidents (user type 3)
 * only; everyone else gets an empty directory. Forms bound to the sign-up /
 * new-event / new-workplan system functions are reached through their own
 * dedicated flows, so they are not listed here.
 */
class FormDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $forms = collect();

        if (Form::isAccessibleBy($request->user())) {
            $forms = Form::query()
                ->whereNotNull('route_name')
                ->where('is_published', true)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('system_function')
                        ->orWhere('system_function', SystemFunction::MEMBERSHIP_REGISTRATION);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'route_name', 'description_text']);
        }

        return view('pages.form.directory', [
            'title' => 'Forms',
            'forms' => $forms->map(fn (Form $form) => [
                'name' => $form->name,
                'purpose' => (string) ($form->description_text ?? ''),
                'url' => url('/forms/'.$form->route_name),
            ])->values(),
        ]);
    }
}
