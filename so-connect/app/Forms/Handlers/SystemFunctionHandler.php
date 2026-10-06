<?php

namespace App\Forms\Handlers;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Routes a bound form page's submission into its system function's domain flow
 * (see {@see \App\Forms\SystemFunction}). Handlers implement the
 * request-approval lifecycle: they store a request (and document) for an admin
 * to approve — the domain write happens at approval, not at submission.
 *
 * Because bound forms are authored freely in the builder, handlers read the
 * submission payload by well-known field keys (a field's `field_key`, or the
 * universal key it is mapped to) and must fail with a helpful message when a
 * required key is missing.
 */
interface SystemFunctionHandler
{
    /**
     * Validate the assembled payload BEFORE the submission row is created.
     *
     * @param  array<string,mixed>  $payload  field_key => value
     *
     * @throws \Illuminate\Validation\ValidationException when required
     *         well-known keys are missing/invalid
     */
    public function validatePayload(Form $form, array $payload, Request $request): void;

    /**
     * Create the function's pending request row(s) for the stored submission
     * and redirect the submitter with a status message.
     *
     * @param  array<string,mixed>  $payload  field_key => value
     */
    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse;
}
