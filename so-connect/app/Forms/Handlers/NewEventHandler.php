<?php

namespace App\Forms\Handlers;

use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use App\Services\WorkplanService;
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

    // Only the organization binding is structurally required. Every other
    // (non-special) field is optional so officers aren't blocked mid-form; a
    // blank target date defaults to today in handle() (the column is NOT NULL).
    private const REQUIRED_KEYS = ['organization_id'];

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

        // Target date is optional; only validate it when the officer supplied
        // one. A blank date is defaulted to today at handle() time.
        $targetDate = $this->payloadValue($form, $payload, 'target_date');
        if ($targetDate !== null && $targetDate !== '') {
            try {
                if (Carbon::parse((string) $targetDate)->startOfDay()->lt(Carbon::today())) {
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
    }

    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->getKey();
        $organizationId = (int) $this->payloadValue($form, $payload, 'organization_id');

        // target_date is optional but event_plans.target_date is NOT NULL — fall
        // back to today when the officer left it blank.
        $rawTargetDate = $this->payloadValue($form, $payload, 'target_date');
        $targetDate = ($rawTargetDate === null || $rawTargetDate === '')
            ? Carbon::today()->toDateString()
            : (string) $rawTargetDate;

        $sharedPlanFields = [
            'organization_id' => $organizationId,
            'created_by' => $userId,
            'title' => (string) $this->payloadValue($form, $payload, 'title'),
            'target_date' => $targetDate,
            'purpose_of_activity' => $this->payloadValue($form, $payload, 'purpose_of_activity'),
            'resources_needed' => $this->payloadValue($form, $payload, 'resources_needed'),
        ];

        $hasApprovedWorkplan = app(WorkplanService::class)
            ->hasApprovedWorkplanForDate($organizationId, $targetDate);

        // Without an approved workplan for this date, retain a parent plan so
        // the approved activity is available to the next workplan form.
        $parentPlan = $hasApprovedWorkplan
            ? null
            : EventPlan::query()->create(array_merge($sharedPlanFields, ['status' => 'pending']));

        $actionRequest = app(DocumentGenerationService::class)->createDocumentGenerationRequest(
            $organizationId,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            $userId,
        );

        // event_start_time/event_end_time are Time-only fields (H:i); combine
        // them with the target date so the stored value is a real datetime.
        // event_start_time/event_end_time are Time-only (H:i); combine each with
        // the target date into a real datetime, or leave null when not provided.
        $startTime = $this->payloadValue($form, $payload, 'event_start_time');
        $endTime = $this->payloadValue($form, $payload, 'event_end_time');

        $childPlan = EventPlan::query()->create(array_merge($sharedPlanFields, [
            'event_location' => $this->payloadValue($form, $payload, 'event_location'),
            'event_start_time' => ($startTime === null || $startTime === '') ? null : $targetDate.' '.(string) $startTime,
            'event_end_time' => ($endTime === null || $endTime === '') ? null : $targetDate.' '.(string) $endTime,
            'status' => 'pending',
            'parent_plan_id' => $parentPlan?->getKey(),
            'request_id' => (int) $actionRequest->getKey(),
        ]));

        $actionRequest->update([
            'payload' => array_merge((array) ($actionRequest->payload ?? []), [
                'event_plan_id' => (int) $childPlan->getKey(),
                'parent_plan_id' => $parentPlan?->getKey(),
            ]),
        ]);

        return redirect()
            ->route('forms.render', $form->route_name)
            ->with('success', 'Event request submitted. Awaiting admin approval.');
    }
}
