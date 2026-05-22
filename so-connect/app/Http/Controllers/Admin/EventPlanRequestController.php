<?php

namespace App\Http\Controllers\Admin;

use App\Mail\EventApprovedMail;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Event;
use App\Models\Event\EventDetail;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Models\User;
use App\Services\DocumentGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class EventPlanRequestController extends Controller
{
    public function index()
    {
        $requests = ActionRequest::query()
            ->where('action_type', 10)
            ->orderByDesc('requested_at')
            ->get(['request_id', 'action_type', 'organization_id', 'requested_by', 'user', 'payload', 'requested_at']);

        $requestIds = $requests->pluck('request_id')->all();

        $approvals = Approval::query()
            ->whereIn('request', $requestIds)
            ->get()
            ->keyBy('request');

        $eventPlanIds = $requests->map(fn ($r) => (int) (((array) ($r->payload ?? []))['event_plan_id'] ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $eventPlans = EventPlan::query()
            ->whereIn('event_plan_id', $eventPlanIds)
            ->get(['event_plan_id', 'title', 'target_date', 'organization_id', 'status'])
            ->keyBy('event_plan_id');

        $requesterIds = $requests->pluck('user')->filter()->unique()->values()->all();
        $requesterNames = [];
        if (! empty($requesterIds)) {
            $requesterNames = DB::table('users as u')
                ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                ->whereIn('u.user_id', $requesterIds)
                ->select('u.user_id', DB::raw("TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) as name"))
                ->get()->pluck('name', 'user_id')->all();
        }

        $orgIds = $requests->pluck('organization_id')->filter()->unique()->merge(
            $eventPlans->pluck('organization_id')
        )->unique()->values()->all();

        $orgNames = [];
        if (! empty($orgIds)) {
            $orgNames = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $orgIds)
                ->select('o.organization_id', DB::raw("COALESCE(od.name, 'Unknown Organization') as name"))
                ->get()->pluck('name', 'organization_id')->all();
        }

        $rows = $requests->map(function ($req) use ($approvals, $eventPlans, $requesterNames, $orgNames) {
            $payload = (array) ($req->payload ?? []);
            $planId = (int) ($payload['event_plan_id'] ?? 0);
            $plan = $planId > 0 ? $eventPlans->get($planId) : null;
            $approval = $approvals->get($req->request_id);
            $orgId = (int) ($req->organization_id ?? ($plan?->organization_id ?? 0));

            return [
                'request' => $req,
                'plan' => $plan,
                'approval' => $approval,
                'requester_name' => $requesterNames[$req->user] ?? 'Unknown',
                'org_name' => $orgNames[$orgId] ?? 'Unknown Organization',
            ];
        });

        $pending = $rows->filter(fn ($r) => $r['approval'] === null)->values();
        $decided = $rows->filter(fn ($r) => $r['approval'] !== null)->take(30)->values();

        return view('pages.admin.event-plan-requests.index', [
            'title' => 'Event Plan Requests',
            'pending' => $pending,
            'decided' => $decided,
        ]);
    }

    public function decide(Request $request, int $requestId): RedirectResponse
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $actionRequest = ActionRequest::query()->findOrFail($requestId);

        if ((int) $actionRequest->action_type !== 10) {
            return back()->withErrors(['request' => 'This request is not an event plan request.']);
        }

        $approval = Approval::query()->updateOrCreate(
            ['request' => $requestId],
            [
                'admin' => $userId,
                'approved_at' => now(),
                'is_rejected' => $validated['decision'] === 'reject',
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]
        );

        $payload = (array) ($actionRequest->payload ?? []);
        $eventPlanId = (int) ($payload['event_plan_id'] ?? 0);
        $isApproved = $validated['decision'] === 'approve';

        if ($eventPlanId > 0) {
            $plan = EventPlan::query()->find($eventPlanId);

            if ($plan && $isApproved && $plan->isEventRequest()) {
                $eventDetail = EventDetail::query()->create([
                    'name'       => $plan->title,
                    'location'   => $plan->event_location,
                    'desc_text'  => $plan->event_description ?? '',
                    'start_time' => $plan->event_start_time,
                    'end_time'   => $plan->event_end_time,
                ]);

                $event = Event::query()->create([
                    'organization' => (int) $plan->organization_id,
                    'creator'      => $userId,
                    'event_detail' => (int) $eventDetail->getKey(),
                ]);

                $eventId = (int) $event->getKey();

                $plan->update(['status' => 'approved', 'event_id' => $eventId]);

                EventPlan::query()
                    ->where('event_plan_id', $plan->parent_plan_id)
                    ->update(['event_id' => $eventId]);

                $requester = User::query()->find((int) $plan->created_by);
                if ($requester?->user_email) {
                    try {
                        Mail::to($requester->user_email)
                            ->send(new EventApprovedMail($plan, $this->resolveUserName((int) $plan->created_by)));
                    } catch (\Throwable) {
                    }
                }

                $roForm = Form::query()->where('route_name', 'activity-request')->first();
                if ($roForm) {
                    try {
                        $submission = FormSubmission::query()->create([
                            'form_id' => (int) $roForm->getKey(),
                            'organization_id' => (int) $plan->organization_id,
                            'submitted_by' => (int) $plan->created_by,
                            'payload' => $this->buildRoPayloadFromPlan($plan),
                            'submitted_at' => now(),
                        ]);

                        app(DocumentGenerationService::class)->createDocumentGenerationRequest(
                            (int) $plan->organization_id,
                            (int) $submission->getKey(),
                            (int) $roForm->getKey(),
                            (int) $plan->created_by,
                        );
                    } catch (\Throwable) {
                        // Request queued; skip if the form or template is not ready yet.
                    }
                }
            } elseif ($plan) {
                $plan->update(['status' => $isApproved ? 'approved' : 'rejected']);
            }
        }

        $message = $isApproved ? 'Event plan approved successfully.' : 'Event plan rejected.';

        return redirect()->route('admin.event-plan-requests.index')->with('success', $message);
    }

    private function buildRoPayloadFromPlan(EventPlan $plan): array
    {
        $targetDate = $plan->target_date;

        $organizationName = DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->where('o.organization_id', (int) $plan->organization_id)
            ->select(DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"))
            ->value('organization_name') ?? 'Unknown Organization';

        return [
            'date' => $targetDate instanceof \DateTimeInterface ? $targetDate->format('Y-m-d') : '',
            'organization' => $organizationName,
            'projectActivity' => (string) ($plan->title ?? ''),
            'purposed' => (string) ($plan->purpose_of_activity ?? ''),
            'dayOfTheWeek' => $targetDate instanceof \DateTimeInterface ? $targetDate->format('l') : '',
            'time' => $plan->event_start_time && $plan->event_end_time
                ? $plan->event_start_time->format('g:i A') . ' - ' . $plan->event_end_time->format('g:i A')
                : '',
            'placeAndVenue' => (string) ($plan->event_location ?? ''),
            'facilitiesOrEquipmentToBeUsedRow' => array_values(array_filter((array) ($plan->university_facilities ?? []), fn ($value) => filled($value))),
            'activityTypes' => array_values(array_filter((array) ($plan->activity_types ?? []), fn ($value) => filled($value))),
            'activityTypeOther' => (string) ($plan->activity_types_other ?? ''),
            'areaScope' => (string) ($plan->area_scope ?? ''),
            'areaScopeOther' => (string) ($plan->area_scope_other ?? ''),
            'sponsor' => (string) ($plan->sponsor ?? ''),
            'sponsorOther' => (string) ($plan->sponsor_other ?? ''),
            'extensionServices' => $plan->extension_services === null ? '' : ($plan->extension_services ? 'yes' : 'no'),
            'presidentName' => (string) ($plan->president_name ?? ''),
            'presidentContactNo' => (string) ($plan->president_contact ?? ''),
            'adviserRow' => array_values(array_filter((array) ($plan->faculty_advisers ?? []), fn ($value) => filled($value))),
            'collegeDean' => (string) ($plan->college_dean ?? ''),
            'organization_id' => (int) $plan->organization_id,
        ];
    }

    private function resolveUserName(int $userId): string
    {
        $profileRow = DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->where('u.user_id', $userId)
            ->select(['p.first_name', 'p.middle_name', 'p.last_name'])
            ->first();

        if (! $profileRow) {
            return 'Officer';
        }

        return trim(implode(' ', array_filter([
            $profileRow->first_name,
            $profileRow->middle_name,
            $profileRow->last_name,
        ]))) ?: 'Officer';
    }
}
