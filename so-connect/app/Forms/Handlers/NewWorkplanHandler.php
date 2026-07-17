<?php

namespace App\Forms\Handlers;

use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\DocumentGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * New Workplan: a bound form's submission stores the workplan document data
 * (built from the officer's SELECTED approved events) and files a
 * document-generation request for admin approval — approval generates the
 * workplan PDF, rejection sends the submitter back to the form.
 *
 * The submission's `workplan_events` field (the multi-select of approved
 * events) is expanded here into the same activities/target/people/resources
 * arrays the legacy WorkplanController::generatePdf produced, plus a
 * `workplan_rows` list for the printed-template's repeating table token.
 */
class NewWorkplanHandler implements SystemFunctionHandler
{
    use ResolvesPayloadKeys;

    public function validatePayload(Form $form, array $payload, Request $request): void
    {
        $ids = $this->selectedEventIds($form, $payload);
        if ($ids === []) {
            throw ValidationException::withMessages([
                'form' => 'Select at least one approved event to include in the workplan.',
            ]);
        }

        $plans = EventPlan::query()->whereIn('event_plan_id', $ids)->get();

        $missing = [];
        foreach ($plans as $plan) {
            if (trim((string) ($plan->resources_needed ?? '')) === '') {
                $missing[] = "\"{$plan->title}\" is missing its Resources Needed.";
            }
            if (empty($plan->persons_responsible ?? [])) {
                $missing[] = "\"{$plan->title}\" is missing its Persons Responsible.";
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages(['form' => implode(' ', $missing)]);
        }
    }

    public function handle(Form $form, FormSubmission $submission, array $payload, Request $request): RedirectResponse
    {
        $ids = $this->selectedEventIds($form, $payload);
        $plans = EventPlan::query()
            ->whereIn('event_plan_id', $ids)
            ->orderBy('target_date')
            ->get();

        $organizationId = (int) ($plans->first()?->organization_id ?? 0);
        $personNames = $this->personNames($plans);

        $rows = $plans->map(function (EventPlan $plan) use ($personNames) {
            $people = collect($plan->persons_responsible ?? [])
                ->map(fn ($id) => $personNames[(int) $id] ?? null)
                ->filter()
                ->implode(', ');

            return [
                'title' => (string) $plan->title,
                'target' => optional($plan->target_date)->format('M d, Y') ?? (string) $plan->target_date,
                'resources' => (string) ($plan->resources_needed ?? ''),
                'people' => $people,
            ];
        })->values();

        $orgName = (string) (DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', $organizationId)
            ->value('od.name') ?? 'Unknown Organization');

        // Enrich the stored submission so the printed template resolves the
        // activities: `workplan_rows` drives the repeating-row token; the
        // parallel arrays mirror the legacy payload shape.
        $enriched = array_merge($payload, [
            'organization_id' => $organizationId,
            'organization' => $orgName,
            'workplan_rows' => $rows->all(),
            'activities' => $rows->pluck('title')->all(),
            'target' => $rows->pluck('target')->all(),
            'people' => $rows->pluck('people')->all(),
            'resources' => $rows->pluck('resources')->all(),
        ]);

        $submission->forceFill(['organization_id' => $organizationId, 'payload' => $enriched])->save();

        app(DocumentGenerationService::class)->createDocumentGenerationRequest(
            $organizationId,
            (int) $submission->getKey(),
            (int) $form->getKey(),
            (int) $request->user()->getKey(),
        );

        return redirect()
            ->route('forms.render', $form->route_name)
            ->with('success', 'Workplan request submitted and is pending admin review.');
    }

    /**
     * The selected approved-event ids, from the form's workplan-events field
     * (resolved by key or by the literal `workplan_events` payload key).
     *
     * @param  array<string,mixed>  $payload
     * @return int[]
     */
    private function selectedEventIds(Form $form, array $payload): array
    {
        foreach ($form->fields as $field) {
            if ($field->field_type === \App\Forms\FieldType::WORKPLAN_EVENTS
                && array_key_exists($field->field_key, $payload)) {
                return array_values(array_filter(array_map('intval', (array) $payload[$field->field_key])));
            }
        }

        return array_values(array_filter(array_map('intval', (array) ($payload['workplan_events'] ?? []))));
    }

    /**
     * @param  \Illuminate\Support\Collection<int,EventPlan>  $plans
     * @return array<int,string>
     */
    private function personNames($plans): array
    {
        $ids = $plans->flatMap(fn (EventPlan $p) => $p->persons_responsible ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('u.user_id', $ids)
            ->select(['u.user_id', DB::raw("TRIM(CONCAT_WS(' ', p.first_name, p.last_name)) as name")])
            ->get()
            ->pluck('name', 'user_id')
            ->map(fn ($n) => (string) $n)
            ->all();
    }
}
