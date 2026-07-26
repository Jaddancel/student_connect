<?php

namespace App\Forms\Handlers;

use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * New Event: a bound form's submission creates the pending event-plan +
 * document-generation request pair the direct activity request flow uses, so
 * the existing admin approval (event + calendar entry creation) applies
 * unchanged.
 */
class NewEventHandler implements SystemFunctionHandler
{
    use ResolvesPayloadKeys;

    private const REQUIRED_KEYS = [
        'organization_id', 'title', 'target_date',
        'event_location', 'event_start_time', 'event_end_time',
    ];

    public function validatePayload(Form $form, array $payload, Request $request): void
    {
        $values = $this->requirePayloadKeys($form, $payload, self::REQUIRED_KEYS);
        $userId = (int) $request->user()->getKey();
        $organizationId = (int) $values['organization_id'];

        $isOrganizationMember = DB::table('organization_officers')
            ->where('user', $userId)
            ->where('organization', $organizationId)
            ->exists();
        if ((int) $request->user()->user_type !== 2 && ! $isOrganizationMember) {
            throw ValidationException::withMessages([
                'form' => 'You can only submit event requests for organizations you belong to.',
            ]);
        }

        try {
            if (Carbon::parse((string) $values['target_date'])->startOfDay()->lt(Carbon::today())) {
                throw ValidationException::withMessages([
                    'form' => 'Event requests cannot target a past date.',
                ]);
            }
        } catch (\Carbon\Exceptions\InvalidFormatException) {
            throw ValidationException::withMessages([
                'form' => 'The target date could not be understood.',
            ]);
        }
    }

    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->getKey();
        $organizationId = (int) $this->payloadValue($form, $payload, 'organization_id');
        $targetDate = (string) $this->payloadValue($form, $payload, 'target_date');

        $sharedPlanFields = [
            'organization_id' => $organizationId,
            'created_by' => $userId,
            'title' => (string) $this->payloadValue($form, $payload, 'title'),
            'target_date' => $targetDate,
            'purpose_of_activity' => $this->payloadValue($form, $payload, 'purpose_of_activity'),
            'resources_needed' => $this->payloadValue($form, $payload, 'resources_needed'),
        ];

        // Same shape the direct activity request creates: a pending parent
        // plan (the tally/scoring anchor) plus a pending child carrying the
        // schedule, tied to the document-generation request the admin decides.
        // The parent stays pending until that decision — approval is what
        // admits the activity to the org's workplan, rejection kills it.
        $parentPlan = EventPlan::query()->create(array_merge($sharedPlanFields, ['status' => 'pending']));

        $actionRequest = app(DocumentGenerationService::class)->createDocumentGenerationRequest(
            $organizationId,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        // event_start_time/event_end_time are Time-only fields (H:i); combine
        // them with the target date so the stored value is a real datetime.
        $childPlan = EventPlan::query()->create(array_merge($sharedPlanFields, [
            'event_location' => (string) $this->payloadValue($form, $payload, 'event_location'),
            'event_start_time' => $targetDate.' '.(string) $this->payloadValue($form, $payload, 'event_start_time'),
            'event_end_time' => $targetDate.' '.(string) $this->payloadValue($form, $payload, 'event_end_time'),
            'status' => 'pending',
            'parent_plan_id' => (int) $parentPlan->getKey(),
            'request_id' => (int) $actionRequest->getKey(),
        ]));

        $actionRequest->update([
            'payload' => array_merge((array) ($actionRequest->payload ?? []), [
                'event_plan_id' => (int) $childPlan->getKey(),
                'parent_plan_id' => (int) $parentPlan->getKey(),
            ]),
        ]);

        return redirect()
            ->route('forms.render', $form->route_name)
            ->with('success', 'Event request submitted. Awaiting admin approval.');
    }
}
