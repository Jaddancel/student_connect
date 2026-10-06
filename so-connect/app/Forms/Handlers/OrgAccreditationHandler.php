<?php

namespace App\Forms\Handlers;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Organization Accreditation: a bound form's submission is re-checked
 * server-side and then routed into the same document-generation request an
 * unbound form produces — so the existing review + document flow is unchanged,
 * while AccreditationService measures compliance from the approved request.
 */
class OrgAccreditationHandler implements SystemFunctionHandler
{
    use ResolvesPayloadKeys;

    public function validatePayload(Form $form, array $payload, Request $request): void
    {
        $organizationId = $this->organizationId($form, $payload, $request->user());

        if ($organizationId <= 0) {
            throw ValidationException::withMessages([
                'form' => 'Choose the organization this application is for — you are not an officer of any organization.',
            ]);
        }
        if (! DB::table('organizations')->where('organization_id', $organizationId)->exists()) {
            throw ValidationException::withMessages([
                'form' => 'The selected organization does not exist.',
            ]);
        }
    }

    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse
    {
        $userId = (int) ($request->user()?->getKey() ?? 0);
        $organizationId = $this->organizationId($form, $payload, $request->user());

        app(DocumentGenerationService::class)->createDocumentGenerationRequest(
            $organizationId ?: null,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        return redirect()->route('forms.render', $form->route_name)
            ->with('success', 'Accreditation application submitted for approval.');
    }
}
