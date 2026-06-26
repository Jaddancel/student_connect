<?php

namespace App\Http\Middleware;

use App\Models\Form;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFormTemplateAssigned
{
    /**
     * Block a form page when its document template has not been assigned by an
     * admin. A template is "assigned" once an active Template row exists for the
     * form. Until then every visitor (admins included) sees a standalone block
     * instead of the form, while the route itself stays live.
     */
    public function handle(Request $request, Closure $next, string $routeName): Response
    {
        $form = Form::query()->where('route_name', $routeName)->first();

        $hasActiveTemplate = $form
            && $form->templates()->where('is_active', true)->exists();

        if (! $hasActiveTemplate) {
            return response()->view('pages.form.unavailable', [
                'form'  => $form,
                'title' => $form?->name ?? 'Form Unavailable',
            ], 503);
        }

        return $next($request);
    }
}
