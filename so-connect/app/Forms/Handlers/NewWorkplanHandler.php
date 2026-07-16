<?php

namespace App\Forms\Handlers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * New Workplan: a bound form's submission stores the workplan document data
 * and files a document-generation request for admin approval — approval
 * generates the workplan PDF, rejection sends the submitter back to the form.
 */
class NewWorkplanHandler implements SystemFunctionHandler
{
    use ResolvesPayloadKeys;

    public function validatePayload(Form $form, array $payload, Request $request): void
    {
        $this->requirePayloadKeys($form, $payload, ['organization_id']);
    }

    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse
    {
        app(DocumentGenerationService::class)->createDocumentGenerationRequest(
            (int) $this->payloadValue($form, $payload, 'organization_id'),
            (int) $submission->getKey(),
            (int) $form->getKey(),
            (int) $request->user()->getKey(),
        );

        return redirect()
            ->route('forms.render', $form->route_name)
            ->with('success', 'Workplan request submitted and is pending admin review.');
    }
}
