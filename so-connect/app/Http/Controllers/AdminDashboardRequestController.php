<?php

namespace App\Http\Controllers;

use App\Forms\SystemFunction;
use App\Helpers\FormTemplateHelper;
use App\Models\Approval;
use App\Models\EventPlan;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Request as ActionRequest;
use App\Services\AccreditationService;
use App\Support\SignupRequests;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Feeds the user-type-2 dashboard sections:
 *   membership → New User (sign-up) + Organization Membership requests
 *   events     → Activity requests (the form bound to NEW_EVENT)
 *   policy     → requests filed from the forms required by the
 *                accreditation conditions settings
 *
 * Each request carries its resolved decision (pending/approved/rejected) so
 * the dashboard does not need to understand per-kind approval stages.
 */
class AdminDashboardRequestController extends Controller
{
    public const SECTIONS = ['membership', 'events', 'policy'];

    private const MEMBERSHIP_ACTION_TYPE = 1;

    public function index(Request $request, string $section): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ((int) $user->user_type !== 2) {
            return response()->json([
                'message' => 'You are not authorized to access dashboard requests.',
            ], 403);
        }

        $hours = max((int) $request->query('hours', 0), 0);

        $query = $this->sectionQuery($section);

        if ($query === null) {
            return response()->json(['data' => []]);
        }

        if ($hours > 0) {
            $query->whereBetween('requested_at', [now()->subHours($hours), now()]);
        }

        $requests = $query
            ->orderByDesc('requested_at')
            ->orderByDesc('request_id')
            ->get(['request_id', 'action', 'action_type', 'form_id', 'organization_id', 'user', 'payload', 'requested_at']);

        return response()->json(['data' => $this->present($requests)]);
    }

    private function sectionQuery(string $section): ?Builder
    {
        return match ($section) {
            'membership' => ActionRequest::query()
                ->whereIn('action_type', [SignupRequests::ACTION_TYPE, self::MEMBERSHIP_ACTION_TYPE]),
            'events' => $this->formRequestsQuery(
                array_filter([(int) (SystemFunction::form(SystemFunction::NEW_EVENT)?->getKey() ?? 0)]),
                FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION,
            ),
            'policy' => $this->formRequestsQuery(app(AccreditationService::class)->requiredFormIds()),
            default => null,
        };
    }

    /**
     * Requests originating from any of $formIds, matched on the indexed
     * column or the submission payload (mirrors AccreditationService).
     *
     * @param  array<int,int>  $formIds
     */
    private function formRequestsQuery(array $formIds, ?int $actionType = null): ?Builder
    {
        $formIds = array_values(array_filter(array_map('intval', $formIds), fn ($id) => $id > 0));

        if ($formIds === []) {
            return null;
        }

        return ActionRequest::query()
            ->when($actionType !== null, fn ($q) => $q->where('action_type', $actionType))
            ->where(function ($q) use ($formIds) {
                $q->whereIn('form_id', $formIds);

                foreach ($formIds as $formId) {
                    $q->orWhere('payload->form_id', $formId);
                }
            });
    }

    /**
     * @param  Collection<int,ActionRequest>  $requests
     * @return array<int,array<string,mixed>>
     */
    private function present(Collection $requests): array
    {
        if ($requests->isEmpty()) {
            return [];
        }

        $requestIds = $requests->pluck('request_id')->map(fn ($id) => (int) $id)->all();
        $approvalsByRequest = Approval::query()
            ->whereIn('request', $requestIds)
            ->get(['approval_id', 'request', 'stage', 'is_rejected', 'approved_at'])
            ->groupBy(fn ($approval) => (int) $approval->request);

        $profiles = $this->profilesByUser($requests->pluck('user')->filter()->unique()->values()->all());
        $organizationNames = $this->organizationNames(
            $requests->map(fn ($r) => $this->organizationIdFor($r))->filter()->unique()->values()->all()
        );
        $formNames = Form::query()
            ->whereIn('id', $requests->map(fn ($r) => $this->formIdFor($r))->filter()->unique()->values()->all())
            ->pluck('name', 'id');
        $activityTitles = $this->activityTitles($requests);

        return $requests->map(function (ActionRequest $r) use ($approvalsByRequest, $profiles, $organizationNames, $formNames, $activityTitles) {
            $payload = (array) ($r->payload ?? []);
            $actionType = (int) $r->action_type;
            $profile = $profiles[(int) $r->user] ?? null;
            $requesterName = $this->requesterName($profile, $payload, (int) $r->user);
            $organizationId = $this->organizationIdFor($r);
            $formName = (string) ($formNames[$this->formIdFor($r)] ?? '');
            [$status, $decidedAt] = $this->decision($actionType, $approvalsByRequest->get((int) $r->request_id) ?? collect());

            $kind = match (true) {
                $actionType === SignupRequests::ACTION_TYPE => 'New User',
                $actionType === self::MEMBERSHIP_ACTION_TYPE => 'Organization Membership',
                isset($activityTitles[(int) $r->request_id]) => 'Activity',
                default => $formName !== '' ? $formName : 'Form Submission',
            };

            $nameOrTitle = match (true) {
                $actionType === SignupRequests::ACTION_TYPE,
                $actionType === self::MEMBERSHIP_ACTION_TYPE => $requesterName.' ('.$kind.')',
                isset($activityTitles[(int) $r->request_id]) => $activityTitles[(int) $r->request_id],
                default => $kind.' — '.$requesterName,
            };

            return [
                'request_id' => (int) $r->request_id,
                'action' => (string) $r->action,
                'action_type' => $actionType,
                'form_id' => $this->formIdFor($r) ?: null,
                'user' => $r->user !== null ? (int) $r->user : null,
                'organization_id' => $organizationId ?: null,
                'requested_at' => $r->requested_at?->toIso8601String(),
                'kind' => $kind,
                'profile' => $profile,
                'name_or_title' => $nameOrTitle,
                'request_organization' => $organizationId
                    ? ($organizationNames[$organizationId] ?? 'Organization #'.$organizationId)
                    : 'Organization Pending',
                'approval_status' => $status,
                'decided_at' => $decidedAt,
            ];
        })->values()->all();
    }

    /**
     * Membership is two-stage: only the admin (or legacy stage-less) approval
     * is final, though a president rejection also ends the request. Every
     * other kind is decided by its single approval row.
     *
     * @param  Collection<int,Approval>  $approvals
     * @return array{0:string,1:?string}
     */
    private function decision(int $actionType, Collection $approvals): array
    {
        if ($actionType === self::MEMBERSHIP_ACTION_TYPE) {
            $final = $approvals->first(fn ($a) => $a->stage === null || $a->stage === '' || $a->stage === 'admin')
                ?? $approvals->first(fn ($a) => $a->stage === 'president' && (bool) $a->is_rejected);
        } else {
            $final = $approvals->sortByDesc('approved_at')->first();
        }

        if ($final === null) {
            return ['pending', null];
        }

        return [
            (bool) $final->is_rejected ? 'rejected' : 'approved',
            $final->approved_at ? \Illuminate\Support\Carbon::parse($final->approved_at)->toIso8601String() : null,
        ];
    }

    private function formIdFor(ActionRequest $r): int
    {
        return (int) ($r->form_id ?: (((array) ($r->payload ?? []))['form_id'] ?? 0));
    }

    private function organizationIdFor(ActionRequest $r): int
    {
        $payload = (array) ($r->payload ?? []);
        $organizationId = (int) ($r->organization_id ?: ($payload['organization_id'] ?? 0));

        // Sign-up requests encode the chosen org as "0|<org>|new_officer".
        if ($organizationId <= 0 && (int) $r->action_type === SignupRequests::ACTION_TYPE) {
            $parts = explode('|', (string) $r->action);
            $organizationId = (int) ($parts[1] ?? 0);
        }

        return max($organizationId, 0);
    }

    /**
     * @param  array<string,mixed>|null  $profile
     * @param  array<string,mixed>  $payload
     */
    private function requesterName(?array $profile, array $payload, int $userId): string
    {
        $name = trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? ''));

        // Sign-up applicants have no profile yet; their name is in the request.
        if ($name === '') {
            $name = trim((string) ($payload['first_name'] ?? '').' '.(string) ($payload['last_name'] ?? ''));
        }

        if ($name !== '') {
            return $name;
        }

        return $userId > 0 ? 'User #'.$userId : 'Unknown Requester';
    }

    /**
     * @param  array<int,int>  $userIds
     * @return array<int,array<string,mixed>>
     */
    private function profilesByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('users as u')
            ->join('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('u.user_id', $userIds)
            ->get(['u.user_id', 'p.first_name', 'p.middle_name', 'p.last_name', 'p.occupation'])
            ->mapWithKeys(fn ($row) => [(int) $row->user_id => [
                'first_name' => $row->first_name,
                'middle_name' => $row->middle_name,
                'last_name' => $row->last_name,
                'occupation' => $row->occupation,
            ]])
            ->all();
    }

    /**
     * @param  array<int,int>  $organizationIds
     * @return array<int,string>
     */
    private function organizationNames(array $organizationIds): array
    {
        if ($organizationIds === []) {
            return [];
        }

        return DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('o.organization_id', $organizationIds)
            ->get(['o.organization_id', 'od.name'])
            ->mapWithKeys(fn ($row) => [(int) $row->organization_id => $row->name ?: 'Unknown Organization'])
            ->all();
    }

    /**
     * Activity titles for requests filed from the NEW_EVENT form, resolved
     * the same way as the Activity Requests admin page.
     *
     * @param  Collection<int,ActionRequest>  $requests
     * @return array<int,string> keyed by request id
     */
    private function activityTitles(Collection $requests): array
    {
        $activityFormId = (int) (SystemFunction::form(SystemFunction::NEW_EVENT)?->getKey() ?? 0);

        if ($activityFormId <= 0) {
            return [];
        }

        $activityRequests = $requests->filter(fn (ActionRequest $r) => (int) $r->action_type === FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION
            && $this->formIdFor($r) === $activityFormId);

        if ($activityRequests->isEmpty()) {
            return [];
        }

        $submissionIds = $activityRequests
            ->map(fn ($r) => (int) (((array) ($r->payload ?? []))['submission_id'] ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $submissionTitles = FormSubmission::query()
            ->whereIn('form_submission_id', $submissionIds)
            ->get(['form_submission_id', 'payload'])
            ->mapWithKeys(fn ($s) => [(int) $s->form_submission_id => trim((string) (((array) ($s->payload ?? []))['projectActivity'] ?? ''))])
            ->all();

        $planTitles = EventPlan::query()
            ->whereIn('request_id', $activityRequests->pluck('request_id')->all())
            ->pluck('title', 'request_id')
            ->all();

        return $activityRequests->mapWithKeys(function (ActionRequest $r) use ($submissionTitles, $planTitles) {
            $submissionId = (int) (((array) ($r->payload ?? []))['submission_id'] ?? 0);
            $title = $submissionTitles[$submissionId] ?? '';

            if ($title === '') {
                $title = trim((string) ($planTitles[(int) $r->request_id] ?? ''));
            }

            return [(int) $r->request_id => $title !== '' ? $title : 'Activity Request'];
        })->all();
    }
}
