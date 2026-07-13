<?php

namespace App\Forms\Handlers;

use App\Models\Approval;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\RequestType;
use App\Services\RequestApprovalService;
use App\Services\RequestTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Org Membership Registration: a bound form's submission creates the same
 * action_type=1 membership request the /register flow does (auto-approved when
 * the requester is the org's president).
 */
class MembershipRegistrationHandler implements SystemFunctionHandler
{
    use ResolvesPayloadKeys;

    public function validatePayload(Form $form, array $payload, Request $request): void
    {
        $values = $this->requirePayloadKeys($form, $payload, ['organization_id']);
        $organizationId = (int) $values['organization_id'];
        $userId = (int) $request->user()->getKey();

        if (! DB::table('organizations')->where('organization_id', $organizationId)->exists()) {
            throw ValidationException::withMessages([
                'form' => 'The selected organization does not exist.',
            ]);
        }

        $isAlreadyMember = DB::table('organization_officers')
            ->where('user', $userId)
            ->where('organization', $organizationId)
            ->exists();
        if ($isAlreadyMember) {
            throw ValidationException::withMessages([
                'form' => 'You are already a member of this organization.',
            ]);
        }

        $hasPendingRequest = ActionRequest::query()
            ->where('action_type', 1)
            ->where('action', $organizationId.'|'.$userId)
            ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
            ->exists();
        if ($hasPendingRequest) {
            throw ValidationException::withMessages([
                'form' => 'You already have a pending membership request for this organization.',
            ]);
        }
    }

    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->getKey();
        $organizationId = (int) $this->payloadValue($form, $payload, 'organization_id');

        // Prefer the bound form's own request type (membership-bound forms
        // get one like any other form page); the membership system type is
        // the fallback. action_type=1 keeps approval dispatch identical.
        $requestTypeId = $form->request_type_id
            ? (int) $form->request_type_id
            : (int) app(RequestTypeService::class)->resolveSystemType(
                RequestType::SYSTEM_KEY_MEMBERSHIP,
                'Membership Request',
                RequestType::CATEGORY_ORGANIZATION,
                $userId,
            )->getKey();

        $actionRequest = ActionRequest::query()->create([
            'action' => $organizationId.'|'.$userId,
            'action_type' => 1,
            'request_type_id' => $requestTypeId,
            'form_id' => (int) $form->getKey(),
            'organization_id' => $organizationId,
            'requested_by' => $userId,
            'payload' => array_merge($payload, [
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'form_submission_id' => (int) $submission->getKey(),
            ]),
            'user' => $userId,
            'requested_at' => now(),
        ]);

        app(RequestApprovalService::class)->autoApproveIfPresident($actionRequest, $userId);

        return redirect()
            ->route('forms.render', $form->route_name)
            ->with('success', 'Membership request submitted successfully.');
    }
}
