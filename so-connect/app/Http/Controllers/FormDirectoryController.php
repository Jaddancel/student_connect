<?php

namespace App\Http\Controllers;

use App\Models\Form;
use Illuminate\Http\Request;

/**
 * Public-facing directory of published builder forms, searchable by name and
 * purpose (`description_text`). Complements the global top-bar search with a
 * dedicated browsable page. The literal `/forms` route is registered before the
 * generic `/forms/{routeName}` renderer so it always wins.
 */
class FormDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user && in_array((int) $user->user_type, [1, 2], true);

        $query = Form::query()
            ->whereNotNull('route_name')
            ->where('is_published', true)
            ->where('is_active', true)
            ->orderBy('name');

        $forms = $query->get(['id', 'name', 'route_name', 'description_text', 'sidebar_group']);

        // Non-admins only see forms whose sidebar_group targets a role they hold
        // (or forms with no group restriction).
        if (! $isAdmin && $user) {
            $roles = $user->officers()->pluck('role')->map(fn ($r) => (string) $r)->all();
            $forms = $forms->filter(function (Form $form) use ($roles) {
                $groups = (array) ($form->sidebar_group ?? []);
                if (empty($groups)) {
                    return true;
                }

                return count(array_intersect($groups, $roles)) > 0;
            })->values();
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
