<?php

namespace App\Http\Controllers;

use App\Helpers\FormTemplateHelper;
use App\Models\Approval;
use App\Models\Document;
use App\Models\Form;
use App\Models\Template;
use App\Models\Request as ActionRequest;
use App\Services\OrganizationAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SidebarMenuController extends Controller
{
    public function upcomingEvents(Request $request)
    {
        $user = $request->user();
        $userId = (int) $user->getKey();

        $organizationIds = DB::table('members')
            ->where('user', $userId)
            ->pluck('organization')
            ->map(fn ($organizationId) => (int) $organizationId)
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values();

        $upcomingEvents = collect();
        $isMemberUser = (int) $user->user_type === 3;
        $windowEnd = now()->addDays(30);

        if ($organizationIds->isNotEmpty()) {
            $upcomingEvents = DB::table('events as e')
                ->join('event_details as ed', 'ed.event_detail_id', '=', 'e.event_detail')
                ->leftJoin('organizations as o', 'o.organization_id', '=', 'e.organization')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('e.organization', $organizationIds->all())
                ->where('ed.start_time', '>=', now())
                ->when($isMemberUser, function ($query) use ($windowEnd) {
                    $query->where('ed.start_time', '<=', $windowEnd);
                })
                ->orderBy('ed.start_time')
                ->get([
                    'e.event_id',
                    'ed.name as event_name',
                    'ed.location as event_location',
                    'ed.desc_text as event_description',
                    'ed.start_time',
                    'ed.end_time',
                    DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
                ]);
        }

        return view('pages.sidebar.upcoming-events', [
            'title' => 'Upcoming Events',
            'upcomingEvents' => $upcomingEvents,
            'windowDays' => $isMemberUser ? 30 : null,
        ]);
    }

    public function recentEventRequests(Request $request)
    {
        $userId = (int) $request->user()->getKey();

        $eventRequests = ActionRequest::query()
            ->where('action_type', 2)
            ->where('user', $userId)
            ->orderByDesc('requested_at')
            ->limit(100)
            ->get(['request_id', 'action', 'requested_at']);

        $approvalMap = Approval::query()
            ->whereIn('request', $eventRequests->pluck('request_id')->all())
            ->get(['request', 'is_rejected', 'approved_at'])
            ->keyBy('request');

        $organizationIds = $eventRequests
            ->map(function ($actionRequest) {
                [$organizationId] = $this->parseEventAction($actionRequest->action);

                return $organizationId;
            })
            ->filter(fn ($organizationId) => $organizationId > 0)
            ->unique()
            ->values();

        $organizationNameMap = $this->organizationNameMap($organizationIds);

        $rows = $eventRequests
            ->map(function ($actionRequest) use ($approvalMap, $organizationNameMap) {
                [$organizationId, , $eventName, $startTime, $endTime, $description, $location] = $this->parseEventAction($actionRequest->action);
                $approval = $approvalMap->get((int) $actionRequest->request_id);
                $status = $this->resolveApprovalStatus($approval);

                return [
                    'request_id' => (int) $actionRequest->request_id,
                    'event_name' => $eventName !== '' ? $eventName : 'Untitled Event',
                    'organization_name' => $organizationNameMap[(int) $organizationId] ?? 'Unknown Organization',
                    'location' => $location !== '' ? $location : 'TBA',
                    'description' => $description !== '' ? $description : 'No description provided.',
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'requested_at' => $actionRequest->requested_at,
                    'status' => $status,
                    'status_label' => ucfirst($status),
                    'approved_at' => $approval?->approved_at,
                ];
            })
            ->values();

        return view('pages.sidebar.recent-event-requests', [
            'title' => 'Recent Event Requests',
            'rows' => $rows,
        ]);
    }

    public function approvalRequests(Request $request)
    {
        $userId = (int) $request->user()->getKey();
        $officerOrganizationIds = OrganizationAuthorizationService::officerOrganizationIdsForUser($userId);
        $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);

        if (empty($officerOrganizationIds) && empty($presidentOrganizationIds)) {
            return view('pages.sidebar.approval-requests', [
                'title' => 'Approval Requests',
                'rows' => collect(),
            ]);
        }

        $candidateRequests = ActionRequest::query()
            ->whereIn('action_type', [1, 2, 3, 4, 7, 8])
            ->orderByDesc('requested_at')
            ->limit(300)
            ->get(['request_id', 'action', 'action_type', 'requested_at', 'user']);

        $formIds = $candidateRequests
            ->map(function (ActionRequest $actionRequest) {
                return match ((int) $actionRequest->action_type) {
                    FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION => FormTemplateHelper::decodeDocumentGenerationAction($actionRequest->action)[2],
                    FormTemplateHelper::ACTION_TYPE_FORM_UPLOAD => FormTemplateHelper::decodeFormUploadAction($actionRequest->action)[1],
                    default => 0,
                };
            })
            ->filter(fn (int $formId) => $formId > 0)
            ->unique()
            ->values();

        $templateIds = $candidateRequests
            ->map(function (ActionRequest $actionRequest) {
                if ((int) $actionRequest->action_type !== FormTemplateHelper::ACTION_TYPE_FORM_UPLOAD) {
                    return 0;
                }

                [, , $templateId] = FormTemplateHelper::decodeFormUploadAction($actionRequest->action);

                return $templateId;
            })
            ->filter(fn (int $templateId) => $templateId > 0)
            ->unique()
            ->values();

        $formNameMap = Form::query()
            ->whereIn('id', $formIds->all())
            ->pluck('name', 'id')
            ->all();

        $templateNameMap = Template::query()
            ->whereIn('id', $templateIds->all())
            ->pluck('template_name', 'id')
            ->all();

        $rows = $candidateRequests
            ->map(function (ActionRequest $actionRequest) use ($officerOrganizationIds, $presidentOrganizationIds, $formNameMap, $templateNameMap) {
                $actionType = (int) $actionRequest->action_type;

                if ($actionType === 1) {
                    [$organizationId] = $this->parseMembershipAction($actionRequest->action);

                    if ($organizationId <= 0 || ! in_array($organizationId, $officerOrganizationIds, true)) {
                        return null;
                    }

                    return [
                        'request_id' => (int) $actionRequest->request_id,
                        'action_type' => $actionType,
                        'type_label' => 'Membership Request',
                        'organization_id' => $organizationId,
                        'requester_user_id' => (int) $actionRequest->user,
                        'summary' => 'Membership Registration',
                        'requested_at' => $actionRequest->requested_at,
                    ];
                }

                if ($actionType === 2) {
                    [$organizationId, $requesterUserId, $eventName] = $this->parseEventAction($actionRequest->action);

                    if ($organizationId <= 0 || ! in_array($organizationId, $presidentOrganizationIds, true)) {
                        return null;
                    }

                    return [
                        'request_id' => (int) $actionRequest->request_id,
                        'action_type' => $actionType,
                        'type_label' => 'Event Request',
                        'organization_id' => $organizationId,
                        'requester_user_id' => $requesterUserId > 0 ? $requesterUserId : (int) $actionRequest->user,
                        'summary' => $eventName !== '' ? $eventName : 'Event Approval',
                        'requested_at' => $actionRequest->requested_at,
                    ];
                }

                if ($actionType === FormTemplateHelper::ACTION_TYPE_DOCUMENT_GENERATION) {
                    [$organizationId, $submissionId, $formId, $requesterUserId] = FormTemplateHelper::decodeDocumentGenerationAction($actionRequest->action);

                    if ($organizationId <= 0 || ! in_array($organizationId, $presidentOrganizationIds, true)) {
                        return null;
                    }

                    $formName = (string) ($formNameMap[$formId] ?? ('Form #'.$formId));

                    return [
                        'request_id' => (int) $actionRequest->request_id,
                        'action_type' => $actionType,
                        'type_label' => 'Document Generation Request',
                        'organization_id' => $organizationId,
                        'requester_user_id' => $requesterUserId > 0 ? $requesterUserId : (int) $actionRequest->user,
                        'summary' => $formName.' · Submission #'.$submissionId,
                        'requested_at' => $actionRequest->requested_at,
                    ];
                }

                if ($actionType === FormTemplateHelper::ACTION_TYPE_DOCUMENT_ACCESS) {
                    [$organizationId, $generatedDocumentId, $requesterUserId] = FormTemplateHelper::decodeDocumentAccessAction($actionRequest->action);

                    if ($organizationId <= 0 || ! in_array($organizationId, $presidentOrganizationIds, true)) {
                        return null;
                    }

                    return [
                        'request_id' => (int) $actionRequest->request_id,
                        'action_type' => $actionType,
                        'type_label' => 'Document Access Request',
                        'organization_id' => $organizationId,
                        'requester_user_id' => $requesterUserId > 0 ? $requesterUserId : (int) $actionRequest->user,
                        'summary' => 'Generated Document #'.$generatedDocumentId,
                        'requested_at' => $actionRequest->requested_at,
                    ];
                }

                if ($actionType === FormTemplateHelper::ACTION_TYPE_FORM_UPLOAD) {
                    [$organizationId, $formId, $templateId, $requesterUserId] = FormTemplateHelper::decodeFormUploadAction($actionRequest->action);

                    if ($organizationId <= 0 || ! in_array($organizationId, $presidentOrganizationIds, true)) {
                        return null;
                    }

                    $formName = (string) ($formNameMap[$formId] ?? ('Form #'.$formId));
                    $templateName = (string) ($templateNameMap[$templateId] ?? ('Template #'.$templateId));

                    return [
                        'request_id' => (int) $actionRequest->request_id,
                        'action_type' => $actionType,
                        'type_label' => 'Form Upload Request',
                        'organization_id' => $organizationId,
                        'requester_user_id' => $requesterUserId > 0 ? $requesterUserId : (int) $actionRequest->user,
                        'summary' => $formName.' · '.$templateName,
                        'requested_at' => $actionRequest->requested_at,
                    ];
                }

                [$payloadUserId, $organizationId, $currentRole] = $this->parseRoleChangeAction($actionRequest->action);

                if ($organizationId <= 0 || ! in_array($organizationId, $presidentOrganizationIds, true)) {
                    return null;
                }

                $requestedRole = $this->nextRoleFromCurrent($currentRole);

                return [
                    'request_id' => (int) $actionRequest->request_id,
                    'action_type' => $actionType,
                    'type_label' => 'Role Change Request',
                    'organization_id' => $organizationId,
                    'requester_user_id' => $payloadUserId,
                    'summary' => ucfirst($currentRole).' -> '.ucfirst($requestedRole),
                    'requested_at' => $actionRequest->requested_at,
                ];
            })
            ->filter()
            ->values();

        $approvalMap = Approval::query()
            ->whereIn('request', $rows->pluck('request_id')->all())
            ->get(['request', 'is_rejected'])
            ->keyBy('request');

        $organizationNameMap = $this->organizationNameMap($rows->pluck('organization_id')->unique()->values());
        $requesterNameMap = $this->userNameMap($rows->pluck('requester_user_id')->unique()->filter()->values());

        $rows = $rows
            ->map(function (array $row) use ($approvalMap, $organizationNameMap, $requesterNameMap) {
                $approval = $approvalMap->get((int) $row['request_id']);
                $status = $this->resolveApprovalStatus($approval);

                $row['request_organization'] = $organizationNameMap[(int) $row['organization_id']] ?? 'Unknown Organization';
                $row['requester_name'] = $requesterNameMap[(int) $row['requester_user_id']] ?? 'Unknown User';
                $row['status'] = $status;
                $row['status_label'] = ucfirst($status);
                $row['can_decide'] = $status === 'pending';

                return $row;
            })
            ->values();

        return view('pages.sidebar.approval-requests', [
            'title' => 'Approval Requests',
            'rows' => $rows,
        ]);
    }

    public function uploadForms(Request $request)
    {
        $userId = (int) $request->user()->getKey();

        $documents = Document::query()
            ->where('author', $userId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('pages.sidebar.upload-forms', [
            'title' => 'Upload Forms',
            'documents' => $documents,
        ]);
    }

    public function storeUploadedForm(Request $request)
    {
        $validated = $request->validate([
            'description_text' => ['required', 'string', 'max:255'],
            'form_file' => ['required', 'file', 'max:10240'],
        ]);

        $path = $request->file('form_file')->store('forms', 'public');

        Document::query()->create([
            'description_text' => $validated['description_text'],
            'author' => (int) $request->user()->getKey(),
            'link' => $path,
        ]);

        return back()->with('success', 'Form uploaded successfully.');
    }

    public function downloadFiles(Request $request)
    {
        $queryText = trim((string) $request->query('q', ''));

        $documentsQuery = DB::table('documents as d')
            ->leftJoin('users as u', 'u.user_id', '=', 'd.author')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->select([
                'd.document_id',
                'd.description_text',
                'd.link',
                'd.author',
                'd.created_at',
                'p.first_name',
                'p.middle_name',
                'p.last_name',
            ])
            ->orderByDesc('d.created_at');

        if ($queryText !== '') {
            $documentsQuery->where(function ($query) use ($queryText) {
                $query->where('d.description_text', 'like', '%'.$queryText.'%')
                    ->orWhere('d.link', 'like', '%'.$queryText.'%');
            });
        }

        $documents = $documentsQuery
            ->limit(300)
            ->get()
            ->map(function ($row) {
                $authorName = trim(implode(' ', array_filter([
                    $row->first_name,
                    $row->middle_name,
                    $row->last_name,
                ])));

                $link = (string) $row->link;
                $isExternal = str_starts_with($link, 'http://') || str_starts_with($link, 'https://');

                return (object) [
                    'document_id' => (int) $row->document_id,
                    'description_text' => $row->description_text,
                    'link' => $link,
                    'is_external' => $isExternal,
                    'author_name' => $authorName !== '' ? $authorName : 'Unknown Author',
                    'created_at' => $row->created_at,
                ];
            });

        return view('pages.sidebar.download-files', [
            'title' => 'Download Files',
            'queryText' => $queryText,
            'documents' => $documents,
        ]);
    }

    public function downloadDocument(int $documentId)
    {
        $document = Document::query()->findOrFail($documentId);
        $path = (string) $document->link;

        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($path)) {
            return back()->with('status', 'The selected file is no longer available in storage.');
        }

        return response()->download(Storage::disk('public')->path($path));
    }

    public function requestForms(Request $request)
    {
        $userId = (int) $request->user()->getKey();
        $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);

        $organizations = collect();
        $selectedOrganizationId = (int) $request->query('organization_id', 0);
        $members = collect();
        $pendingRequests = collect();

        if (! empty($presidentOrganizationIds)) {
            $organizations = DB::table('organizations as o')
                ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
                ->whereIn('o.organization_id', $presidentOrganizationIds)
                ->orderBy('od.name')
                ->get([
                    'o.organization_id',
                    DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
                ]);

            if ($selectedOrganizationId <= 0) {
                $selectedOrganizationId = (int) ($organizations->first()->organization_id ?? 0);
            }

            if ($selectedOrganizationId > 0 && in_array($selectedOrganizationId, $presidentOrganizationIds, true)) {
                $members = DB::table('members as m')
                    ->join('users as u', 'u.user_id', '=', 'm.user')
                    ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
                    ->leftJoin('organization_officers as oo', function ($join) {
                        $join->on('oo.member', '=', 'm.member_id')
                            ->on('oo.organization', '=', 'm.organization');
                    })
                    ->where('m.organization', $selectedOrganizationId)
                    ->select([
                        'm.user as user_id',
                        'u.user_email',
                        'p.first_name',
                        'p.middle_name',
                        'p.last_name',
                        DB::raw("COALESCE(oo.`role`, 'member') as member_role"),
                    ])
                    ->orderBy('p.first_name')
                    ->orderBy('p.last_name')
                    ->get()
                    ->unique(fn ($row) => (int) $row->user_id)
                    ->values();

                $pendingRoleRequests = ActionRequest::query()
                    ->where('action_type', 7)
                    ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
                    ->orderByDesc('requested_at')
                    ->limit(200)
                    ->get(['request_id', 'action', 'requested_at']);

                $memberNameMap = $this->userNameMap($members->pluck('user_id')->unique()->values());

                $pendingRequests = $pendingRoleRequests
                    ->map(function ($actionRequest) use ($selectedOrganizationId, $memberNameMap) {
                        [$targetUserId, $organizationId, $currentRole] = $this->parseRoleChangeAction($actionRequest->action);

                        if ($organizationId !== $selectedOrganizationId || $targetUserId <= 0) {
                            return null;
                        }

                        return [
                            'request_id' => (int) $actionRequest->request_id,
                            'member_name' => $memberNameMap[$targetUserId] ?? 'Unknown User',
                            'transition' => ucfirst($currentRole).' -> '.ucfirst($this->nextRoleFromCurrent($currentRole)),
                            'requested_at' => $actionRequest->requested_at,
                        ];
                    })
                    ->filter()
                    ->values();
            }
        }

        return view('pages.sidebar.request-forms', [
            'title' => 'Request Forms',
            'organizations' => $organizations,
            'selectedOrganizationId' => $selectedOrganizationId,
            'members' => $members,
            'pendingRequests' => $pendingRequests,
        ]);
    }

    public function storeRoleChangeRequest(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'organization_id')],
            'target_user_id' => ['required', 'integer', Rule::exists('users', 'user_id')],
        ]);

        $userId = (int) $request->user()->getKey();
        $organizationId = (int) $validated['organization_id'];
        $targetUserId = (int) $validated['target_user_id'];
        $presidentOrganizationIds = OrganizationAuthorizationService::presidentOrganizationIdsForUser($userId);

        if (! in_array($organizationId, $presidentOrganizationIds, true)) {
            return back()->with('status', 'You are not authorized to request role changes for this organization.');
        }

        $memberRow = DB::table('members as m')
            ->leftJoin('organization_officers as oo', function ($join) {
                $join->on('oo.member', '=', 'm.member_id')
                    ->on('oo.organization', '=', 'm.organization');
            })
            ->where('m.organization', $organizationId)
            ->where('m.user', $targetUserId)
            ->select([
                'm.member_id',
                DB::raw("COALESCE(oo.`role`, 'member') as member_role"),
            ])
            ->first();

        if (! $memberRow) {
            return back()->with('status', 'Selected user is not a member of the selected organization.');
        }

        $currentRole = in_array($memberRow->member_role, ['member', 'officer', 'president'], true)
            ? $memberRow->member_role
            : 'member';

        if ($currentRole === 'president') {
            return back()->with('status', 'Selected user is already a president.');
        }

        $payload = $targetUserId.'|'.$organizationId.'|'.$currentRole;

        $hasPendingRequest = ActionRequest::query()
            ->where('action_type', 7)
            ->where('action', $payload)
            ->whereNotIn('request_id', Approval::query()->select('request')->whereNotNull('request'))
            ->exists();

        if ($hasPendingRequest) {
            return back()->with('status', 'A pending role change request for this member already exists.');
        }

        ActionRequest::query()->create([
            'action' => $payload,
            'user' => $userId,
            'action_type' => 7,
        ]);

        return back()->with('success', 'Role change request submitted successfully.');
    }

    private function parseMembershipAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 2) {
            return [0, 0];
        }

        $organizationId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $userId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;

        return [$organizationId, $userId];
    }

    private function parseEventAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 7) {
            return [0, 0, '', '', '', '', ''];
        }

        $organizationId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $requesterUserId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        $eventName = $parts[2];
        $startTime = $parts[3];
        $endTime = $parts[4];
        $description = $parts[5];
        $location = implode('|', array_slice($parts, 6));

        return [$organizationId, $requesterUserId, $eventName, $startTime, $endTime, $description, $location];
    }

    private function parseRoleChangeAction(?string $action): array
    {
        $parts = array_map('trim', explode('|', (string) $action));

        if (count($parts) < 3) {
            return [0, 0, 'member'];
        }

        $userId = ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        $organizationId = ctype_digit($parts[1]) ? (int) $parts[1] : 0;
        $currentRole = in_array($parts[2], ['member', 'officer', 'president'], true)
            ? $parts[2]
            : 'member';

        return [$userId, $organizationId, $currentRole];
    }

    private function nextRoleFromCurrent(string $currentRole): string
    {
        return match ($currentRole) {
            'member' => 'officer',
            'officer' => 'president',
            default => 'president',
        };
    }

    private function resolveApprovalStatus(?Approval $approval): string
    {
        if (! $approval) {
            return 'pending';
        }

        if ($approval->is_rejected === true) {
            return 'rejected';
        }

        if ($approval->is_rejected === false) {
            return 'approved';
        }

        return 'pending';
    }

    /**
     * @param  Collection<int, int>  $organizationIds
     * @return array<int, string>
     */
    private function organizationNameMap(Collection $organizationIds): array
    {
        if ($organizationIds->isEmpty()) {
            return [];
        }

        return DB::table('organizations as o')
            ->leftJoin('organization_details as od', 'od.organization_detail_id', '=', 'o.detail')
            ->whereIn('o.organization_id', $organizationIds->all())
            ->get([
                'o.organization_id',
                DB::raw("COALESCE(od.name, 'Unknown Organization') as organization_name"),
            ])
            ->mapWithKeys(fn ($row) => [(int) $row->organization_id => (string) $row->organization_name])
            ->all();
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return array<int, string>
     */
    private function userNameMap(Collection $userIds): array
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        return DB::table('users as u')
            ->leftJoin('profiles as p', 'p.profile_id', '=', 'u.profile')
            ->whereIn('u.user_id', $userIds->all())
            ->get([
                'u.user_id',
                'u.user_email',
                'p.first_name',
                'p.middle_name',
                'p.last_name',
            ])
            ->mapWithKeys(function ($row) {
                $fullName = trim(implode(' ', array_filter([
                    $row->first_name,
                    $row->middle_name,
                    $row->last_name,
                ])));

                return [(int) $row->user_id => $fullName !== '' ? $fullName : (string) ($row->user_email ?? 'Unknown User')];
            })
            ->all();
    }
}
